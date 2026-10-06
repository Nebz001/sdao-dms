<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Identity\RoleDirectory;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use App\Policies\DocumentPolicy;
use App\Support\AcademicPeriod;
use App\Support\DisplayTimezone;
use App\Support\DocumentUrls;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Read model behind the approver dashboard (every approver except SDAO,
 * who get the admin dashboard). One method per card. Every figure is scoped
 * to $approver — as the resolved approver of a document's current step, as
 * the actor of a transition, or through DocumentPolicy — so another
 * approver's documents never reach this page.
 *
 * All day counts are whole calendar days in Asia/Manila, computed in PHP
 * (never in SQL), and every "how long has this been here" figure reads
 * Document::latestTransition() — never documents.updated_at, which lags
 * behind a partial SDAO approval.
 */
class ApproverDashboardData
{
    private const int NEEDS_REVIEW_LIMIT = 5;

    private const int IN_PROGRESS_LIMIT = 5;

    private const int COMING_UP_DAYS = 14;

    private const int COMING_UP_LIMIT = 4;

    private const int RECENT_DECISIONS_LIMIT = 5;

    /** A document at a step for this many days or more is "stalled" (warning tone). */
    private const int STALLED_AFTER_DAYS = 3;

    /** Past this many days a decision's age is shown as a plain date, not "N days ago". */
    private const int RELATIVE_DATE_DAYS = 7;

    /**
     * Actions that count as "this approver decided" — excludes Advanced/
     * Completed (quorum bookkeeping, not this actor's own decision) and
     * Withdrawn (system-attributed).
     *
     * @var array<int, TransitionAction>
     */
    private const array DECISION_ACTIONS = [
        TransitionAction::Approved,
        TransitionAction::Returned,
        TransitionAction::Rejected,
    ];

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $needsReview = null;

    private function __construct(
        private readonly User $approver,
        private readonly AcademicPeriod $period,
        private readonly ApproverQueue $queue,
        private readonly RoleDirectory $directory,
        private readonly DocumentPolicy $policy,
    ) {}

    public static function for(User $approver, AcademicPeriod $period): self
    {
        return new self(
            $approver,
            $period,
            app(ApproverQueue::class),
            app(RoleDirectory::class),
            app(DocumentPolicy::class),
        );
    }

    /**
     * The greeting and the pills under it — cheap, so it is not deferred.
     * "scope" is null for a role with no scope; "extraRoles" holds one entry
     * per further role assignment or active officer seat.
     *
     * @return array{
     *     greeting: string, lastName: string, roleTitle: string|null, role: string|null,
     *     scope: array{label: string, value: string}|null,
     *     extraRoles: array<int, array{label: string, value: string|null}>,
     * }
     */
    public function header(): array
    {
        $assignments = $this->approverAssignments();
        $primary = $assignments->first();
        $hour = DisplayTimezone::convert(now())->hour;

        return [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'lastName' => $this->approver->last_name ?? $this->approver->name,
            'roleTitle' => $primary?->role->stepLabel(),
            'role' => $primary !== null ? $this->roleLabel($primary->role) : null,
            'scope' => $primary !== null ? $this->scopeOf($primary) : null,
            'extraRoles' => $this->extraRoles($assignments->slice(1)),
        ];
    }

    /**
     * The review banner and the three stat cards, computed together because
     * they all derive from the same pending set.
     *
     * @return array{
     *     banner: array{count: int, document: array<string, mixed>|null},
     *     waiting: array{count: int, oldestDays: int|null, byType: array<int, array{formType: string, label: string, count: int}>},
     *     nextEvent: array<string, mixed>|null,
     *     reviewed: array{approved: int, returned: int, rejected: int, total: int},
     * }
     */
    public function summary(): array
    {
        $entries = $this->needsReviewEntries();
        $soonest = $entries->first(fn (array $e) => $e['daysUntilEvent'] !== null);

        $nextEvent = $entries
            ->filter(fn (array $e) => $e['daysUntilEvent'] !== null
                && $e['daysUntilEvent'] >= 0
                && $e['daysUntilEvent'] <= ApproverQueue::EVENT_SOON_DAYS)
            ->first();

        $countsByType = $entries->countBy('formType');

        return [
            'banner' => [
                'count' => $entries->count(),
                // Documents sort soonest-event-first, so the first one with an
                // event date is the soonest; with none dated, the oldest wait.
                'document' => $soonest ?? $entries->first(),
            ],
            'waiting' => [
                'count' => $entries->count(),
                'oldestDays' => $entries->isEmpty() ? null : (int) $entries->max('daysWithYou'),
                'byType' => $this->receivableFormTypes()
                    ->map(fn (FormType $type) => [
                        'formType' => $type->value,
                        'label' => $type->label(),
                        'count' => (int) ($countsByType[$type->value] ?? 0),
                    ])
                    ->values()
                    ->all(),
            ],
            'nextEvent' => $nextEvent,
            'reviewed' => $this->reviewedThisTerm(),
        ];
    }

    /**
     * "Needs your review": soonest event first, undated documents last.
     *
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function needsReview(): array
    {
        $entries = $this->needsReviewEntries();

        return [
            'total' => $entries->count(),
            'rows' => $entries->take(self::NEEDS_REVIEW_LIMIT)->values()->all(),
        ];
    }

    /**
     * Documents this approver passed on that are still moving through the
     * chain, longest at their current step first. A document leaves the
     * moment it is Approved, Rejected or Returned, or comes back to this
     * approver's own step (then it is in "Needs your review" instead).
     *
     * @return array<int, array{
     *     id: int, title: string, organizationName: string, formTypeLabel: string, href: string,
     *     steps: array<int, array{label: string, state: string}>, trackerLabel: string,
     *     currentRole: string, daysAtStep: int, stalled: bool,
     * }>
     */
    public function approvedInProgress(): array
    {
        $roles = $this->approverRoles();

        $documentIds = DocumentTransition::query()
            ->where('actor_id', $this->approver->id)
            ->where('action', TransitionAction::Approved->value)
            ->pluck('document_id')
            ->unique()
            ->values();

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $this->directory->remembering(fn () => Document::query()
            ->with(['organization', 'workflowTemplate.steps', 'latestTransition', 'transitions'])
            ->whereIn('id', $documentIds)
            ->where('status', DocumentStatus::InReview->value)
            ->get()
            ->filter(fn (Document $d) => $this->policy->isChainApprover($this->approver, $d))
            ->map(fn (Document $d) => $this->trackedRow($d, $roles))
            ->filter()
            ->values());

        return $rows
            ->sortBy([['daysAtStep', 'desc'], ['id', 'asc']])
            ->take(self::IN_PROGRESS_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Approved activities in the next two weeks, soonest first, among the
     * documents this approver can view.
     *
     * @return array<int, array{id: int, title: string, organizationName: string, venue: string, date: string, href: string}>
     */
    public function comingUp(): array
    {
        $today = $this->today();
        $windowEnd = $today->addDays(self::COMING_UP_DAYS);

        /** @var Collection<int, ActivityProposal> $proposals */
        $proposals = $this->directory->remembering(fn () => ActivityProposal::query()
            ->with(['calendarActivity', 'document.organization', 'document.workflowTemplate.steps', 'document.transitions'])
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::Approved->value))
            ->whereHas('calendarActivity', fn ($q) => $q->whereBetween('activity_date', [$today->toDateString(), $windowEnd->toDateString()]))
            ->get()
            ->filter(fn (ActivityProposal $p) => $p->calendarActivity !== null
                && $this->policy->isChainApprover($this->approver, $p->document))
            ->values());

        return $proposals
            ->sortBy(fn (ActivityProposal $p) => $p->calendarActivity->activity_date->toDateString().' '.$p->calendarActivity->start_time)
            ->take(self::COMING_UP_LIMIT)
            ->map(fn (ActivityProposal $p) => [
                'id' => $p->document_id,
                'title' => $p->title,
                'organizationName' => $p->document->organization->name,
                'venue' => $p->calendarActivity->venue,
                'date' => $p->calendarActivity->activity_date->toDateString(),
                'href' => DocumentUrls::pathForReviewer($p->document),
            ])
            ->values()
            ->all();
    }

    /**
     * This approver's latest decision on each of their last few documents.
     *
     * @return array<int, array{id: int, action: string, formTypeLabel: string, documentTitle: string, organizationName: string, whenLabel: string, waitingOnOrg: bool, href: string}>
     */
    public function recentDecisions(): array
    {
        return $this->myDecisions()
            ->with(['document:id,title,form_type,status,organization_id', 'document.organization:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_DECISIONS_LIMIT * 5)
            ->get()
            ->unique('document_id')
            ->take(self::RECENT_DECISIONS_LIMIT)
            ->map(fn (DocumentTransition $t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'formTypeLabel' => $t->document->form_type->label(),
                'documentTitle' => $t->document->title,
                'organizationName' => $t->document->organization->name,
                'whenLabel' => $this->decisionWhenLabel($t->created_at),
                'waitingOnOrg' => $t->action === TransitionAction::Returned
                    && $t->document->status === DocumentStatus::Returned,
                'href' => DocumentUrls::pathForReviewer($t->document),
            ])
            ->values()
            ->all();
    }

    /**
     * Pending documents this approver can act on now (DocumentPolicy::review()),
     * soonest event first, undated documents last, then longest-waiting.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function needsReviewEntries(): Collection
    {
        if ($this->needsReview !== null) {
            return $this->needsReview;
        }

        $gate = Gate::forUser($this->approver);

        /** @var EloquentCollection<int, Document> $pending */
        $pending = $this->queue
            ->pendingFor($this->approver, null, ['activityProposal.calendarActivity'])
            ->filter(fn (Document $d) => $gate->allows('review', $d))
            ->values();

        return $this->needsReview = $pending
            ->map(function (Document $d) {
                $eventDate = $d->activityProposal?->calendarActivity?->activity_date?->toDateString();

                return [
                    'id' => $d->id,
                    'title' => $d->title,
                    'formType' => $d->form_type->value,
                    'formTypeLabel' => $d->form_type->label(),
                    'organizationName' => $d->organization->name,
                    'eventDate' => $eventDate,
                    'daysUntilEvent' => $eventDate !== null ? $this->daysUntil($eventDate) : null,
                    'daysWithYou' => $this->daysSince(ApproverQueue::waitingSince($d)),
                    'href' => DocumentUrls::pathForReviewer($d),
                ];
            })
            ->sort(fn (array $a, array $b) => [
                $a['eventDate'] === null ? 1 : 0, $a['eventDate'] ?? '', -$a['daysWithYou'], $a['id'],
            ] <=> [
                $b['eventDate'] === null ? 1 : 0, $b['eventDate'] ?? '', -$b['daysWithYou'], $b['id'],
            ])
            ->values();
    }

    /**
     * The form types this approver's role(s) take part in, read from the
     * workflow templates (configuration, not code — invariant #1).
     *
     * @return Collection<int, FormType>
     */
    private function receivableFormTypes(): Collection
    {
        $types = WorkflowTemplate::query()
            ->whereHas('steps', fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, $this->approverRoles())))
            ->get()
            ->pluck('form_type')
            ->unique();

        return collect(FormType::cases())->filter(fn (FormType $type) => $types->contains($type))->values();
    }

    /**
     * @return array{approved: int, returned: int, rejected: int, total: int}
     */
    private function reviewedThisTerm(): array
    {
        [$start, $end] = $this->period->termRange();

        $counts = $this->myDecisions()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('action, count(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action');

        $approved = (int) ($counts[TransitionAction::Approved->value] ?? 0);
        $returned = (int) ($counts[TransitionAction::Returned->value] ?? 0);
        $rejected = (int) ($counts[TransitionAction::Rejected->value] ?? 0);

        return [
            'approved' => $approved,
            'returned' => $returned,
            'rejected' => $rejected,
            'total' => $approved + $returned + $rejected,
        ];
    }

    /**
     * One "where my approved documents are now" row, or null when the
     * document no longer belongs there. Steps are read from the document's
     * OWN template by role, since positions differ between chain variants.
     *
     * @param  array<int, Role>  $roles
     * @return array<string, mixed>|null
     */
    private function trackedRow(Document $document, array $roles): ?array
    {
        $steps = $document->workflowTemplate?->steps;

        if ($steps === null) {
            return null;
        }

        /** @var WorkflowStep|null $mine */
        $mine = $steps->first(fn (WorkflowStep $s) => in_array($s->role, $roles, true));
        /** @var WorkflowStep|null $current */
        $current = $steps->firstWhere('position', $document->current_step_position);

        if ($mine === null || $current === null || in_array($current->role, $roles, true)) {
            return null;
        }

        /** @var WorkflowStep|null $previous */
        $previous = $steps->where('position', '<', $mine->position)->last();
        $daysAtStep = $this->daysSince($document->latestTransition->created_at);

        $points = array_values(array_filter([
            $previous !== null ? ['label' => $previous->role->stepLabel(), 'state' => 'done'] : null,
            ['label' => 'You', 'state' => 'done'],
            ['label' => $current->role->stepLabel(), 'state' => 'current'],
            ['label' => 'Done', 'state' => 'upcoming'],
        ]));

        return [
            'id' => $document->id,
            'title' => $document->title,
            'organizationName' => $document->organization->name,
            'formTypeLabel' => $document->form_type->label(),
            'href' => DocumentUrls::pathForReviewer($document),
            'steps' => $points,
            'trackerLabel' => sprintf(
                'Approved by %s, now with %s',
                $previous !== null ? $previous->role->stepLabel().' and you' : 'you',
                $current->role->stepLabel(),
            ),
            'currentRole' => $current->role->stepLabel(),
            'daysAtStep' => $daysAtStep,
            'stalled' => $daysAtStep >= self::STALLED_AFTER_DAYS,
        ];
    }

    /**
     * Every approver-side role assignment (students and SDAO excluded), in
     * the order they were granted — the first one is the primary role.
     *
     * @return Collection<int, RoleAssignment>
     */
    private function approverAssignments(): Collection
    {
        return $this->approver->roleAssignments
            ->reject(fn (RoleAssignment $a) => $a->role === Role::Student || $a->role === Role::SdaoMember)
            ->sortBy('id')
            ->values();
    }

    /**
     * @return array<int, Role>
     */
    private function approverRoles(): array
    {
        return $this->approver->roleAssignments
            ->map(fn (RoleAssignment $a) => $a->role)
            ->reject(fn (Role $r) => $r === Role::Student)
            ->unique()
            ->values()
            ->all();
    }

    private function roleLabel(Role $role): string
    {
        return $role === Role::Dean ? 'College Dean' : $role->label();
    }

    /**
     * The one thing this assignment is scoped to, labelled by what it is, or
     * null for a global role (nothing to show, never a placeholder).
     *
     * @return array{label: string, value: string}|null
     */
    private function scopeOf(RoleAssignment $assignment): ?array
    {
        $assignment->loadMissing(['school', 'program', 'organization']);

        [$label, $value] = match ($assignment->role) {
            Role::Adviser => ['Organization', $assignment->organization?->name],
            Role::ProgramChair => ['Program', $assignment->program?->name],
            Role::Dean, Role::Principal => ['School', $assignment->school?->name],
            default => [null, null],
        };

        return $label !== null && $value !== null ? ['label' => $label, 'value' => $value] : null;
    }

    /**
     * One entry per further approver assignment, then per active officer
     * seat (e.g. "President | PICE").
     *
     * @param  Collection<int, RoleAssignment>  $assignments
     * @return array<int, array{label: string, value: string|null}>
     */
    private function extraRoles(Collection $assignments): array
    {
        $fromAssignments = $assignments->map(fn (RoleAssignment $a) => [
            'label' => $this->roleLabel($a->role),
            'value' => $this->scopeOf($a)['value'] ?? null,
        ]);

        $seats = $this->approver->organizationMemberships()
            ->active()
            ->with('organization:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'label' => $m->position->label(),
                'value' => $m->organization->name,
            ]);

        return $fromAssignments->concat($seats)->values()->all();
    }

    /**
     * @return Builder<DocumentTransition>
     */
    private function myDecisions(): Builder
    {
        return DocumentTransition::query()
            ->where('actor_id', $this->approver->id)
            ->whereIn('action', array_map(fn (TransitionAction $a) => $a->value, self::DECISION_ACTIONS));
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::instance(DisplayTimezone::convert(now())->startOfDay());
    }

    /** Whole Manila calendar days from $at until today. */
    private function daysSince(CarbonInterface $at): int
    {
        $start = CarbonImmutable::instance(DisplayTimezone::convert($at))->startOfDay();

        return max(0, (int) $start->diffInDays($this->today()));
    }

    /** Whole Manila calendar days from today until the event date; negative once it has passed. */
    private function daysUntil(string $date): int
    {
        $event = CarbonImmutable::parse($date, DisplayTimezone::ASIA_MANILA)->startOfDay();

        return (int) $this->today()->diffInDays($event, false);
    }

    private function decisionWhenLabel(CarbonInterface $at): string
    {
        $days = $this->daysSince($at);

        return match (true) {
            $days === 0 => 'Today',
            $days === 1 => '1 day ago',
            $days <= self::RELATIVE_DATE_DAYS => "{$days} days ago",
            default => DisplayTimezone::convert($at)->format('M j'),
        };
    }
}
