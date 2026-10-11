<?php

namespace App\Organizations;

use App\Approval\StepApproverResolver;
use App\Dashboard\StudentDocumentLabel;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\CurrentPeriod;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Everything the officer's "Track Documents" page and the home banner show,
 * read from the documents they may already see (Document::visibleToOfficer(),
 * the list-side twin of canActOnDocument()) and from document_transitions,
 * never from documents.updated_at. Read only: nothing here writes, advances,
 * notifies or decides who may see what.
 *
 * Each document's steps are its OWN workflow template's steps, so a chain that
 * differs (on or off calendar, Senior High School's principal, the report's
 * Adviser then SDAO) is drawn as configured, never from a hard-coded list.
 */
final class StudentTrackData
{
    public const int PER_PAGE = 10;

    /** @var list<string> */
    public const array TABS = ['all', 'action', 'moving', 'approved', 'stopped'];

    /** @var array<string, Collection<int, User>> Current holders by "role:org", so a page resolves each seat once. */
    private array $holderCache = [];

    public function __construct(private readonly StepApproverResolver $resolver) {}

    /**
     * The scope: anything still open (so an old returned document never
     * disappears) plus everything filed in the current academic year.
     *
     * @return Collection<int, Document>
     */
    public function documents(User $user): Collection
    {
        [$start, $end] = CurrentPeriod::get()->academicYearRange();

        return Document::query()
            ->visibleToOfficer($user)
            ->where(fn ($query) => $query
                ->whereIn('status', [
                    DocumentStatus::Draft->value,
                    DocumentStatus::InReview->value,
                    DocumentStatus::Returned->value,
                ])
                ->orWhere(fn ($year) => $year->where('created_at', '>=', $start)->where('created_at', '<', $end)))
            ->with([
                'organization.school',
                'organization.program',
                'submitter:id,name',
                'workflowTemplate.steps',
                'transitions.actor',
                'remarks.author',
                'stepApprovals.user',
                ...StudentDocumentLabel::relations(),
            ])
            ->get();
    }

    /**
     * What the home banner needs, counted from the same documents the page lists.
     *
     * @return array{
     *     returned: int, drafts: int, moving: int, approved: int, approvedYear: int, stopped: int, total: int,
     *     movingByStep: list<array{label: string, count: int}>,
     * }
     */
    public function summary(User $user): array
    {
        $documents = $this->documents($user);
        [$termStart, $termEnd] = CurrentPeriod::get()->termRange();

        $moving = $documents->filter(fn (Document $d) => $d->status === DocumentStatus::InReview);

        $byStep = $moving
            ->map(fn (Document $d) => $this->stepFor($d, $d->current_step_position)?->role)
            ->filter()
            ->map(fn (Role $role) => self::stepLabel($role))
            ->countBy()
            ->sortDesc();

        return [
            'returned' => $documents->where('status', DocumentStatus::Returned)->count(),
            'drafts' => $documents->where('status', DocumentStatus::Draft)->count(),
            'moving' => $moving->count(),
            'approved' => $documents
                ->filter(fn (Document $d) => $d->status === DocumentStatus::Approved)
                ->filter(function (Document $d) use ($termStart, $termEnd) {
                    $decidedAt = $d->transitions->firstWhere('action', TransitionAction::Completed)?->created_at ?? $d->created_at;

                    return $decidedAt >= $termStart && $decidedAt < $termEnd;
                })
                ->count(),
            'approvedYear' => $documents->where('status', DocumentStatus::Approved)->count(),
            'stopped' => $documents->where('status', DocumentStatus::Rejected)->count(),
            'total' => $documents->count(),
            'movingByStep' => $byStep->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])->values()->all(),
        ];
    }

    /**
     * One page of the tracker.
     *
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     counts: array<string, int>,
     *     tab: string,
     *     meta: array<string, int|null>,
     *     links: array{prev: string|null, next: string|null},
     * }
     */
    public function page(User $user, string $tab, string $search, int $page): array
    {
        $needle = mb_strtolower(trim($search));

        // The counts follow the search but not the tab, so each tab shows what
        // choosing it would give (same idea as the other student lists).
        $searched = $this->documents($user)
            ->filter(fn (Document $d) => $needle === '' || str_contains(mb_strtolower(StudentDocumentLabel::title($d)), $needle));

        $counts = ['all' => $searched->count()];
        foreach (array_slice(self::TABS, 1) as $key) {
            $counts[$key] = $searched->filter(fn (Document $d) => self::bucket($d) === $key)->count();
        }

        $tab = in_array($tab, self::TABS, true) ? $tab : 'all';

        $shown = $searched
            ->filter(fn (Document $d) => $tab === 'all' || self::bucket($d) === $tab)
            ->sortByDesc(fn (Document $d) => ($d->transitions->max('created_at') ?? $d->created_at)?->getTimestamp())
            ->values();

        $paginator = new LengthAwarePaginator(
            $shown->forPage($page, self::PER_PAGE)->values(),
            $shown->count(),
            self::PER_PAGE,
            $page,
        );

        return [
            'rows' => $paginator->getCollection()->map(fn (Document $d) => $this->row($d))->all(),
            'counts' => $counts,
            'tab' => $tab,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'prev' => $paginator->currentPage() > 1 ? (string) ($paginator->currentPage() - 1) : null,
                'next' => $paginator->hasMorePages() ? (string) ($paginator->currentPage() + 1) : null,
            ],
        ];
    }

    /** The tab a document belongs to. Drafts and returned documents wait on the officer. */
    public static function bucket(Document $document): string
    {
        return match ($document->status) {
            DocumentStatus::Draft, DocumentStatus::Returned => 'action',
            DocumentStatus::InReview => 'moving',
            DocumentStatus::Approved => 'approved',
            DocumentStatus::Rejected => 'stopped',
        };
    }

    /** The stage's name as people say it: "College Dean", "SDAO", "Adviser". */
    public static function stepLabel(Role $role): string
    {
        return match ($role) {
            Role::SdaoMember => 'SDAO',
            Role::Dean => 'College Dean',
            Role::AssistantDirectorAcademicServices => 'Asst. Director',
            default => $role->label(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Document $document): array
    {
        $isDraft = $document->status === DocumentStatus::Draft;
        $returned = $document->status === DocumentStatus::Returned
            ? $document->transitions->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Returned)->sortBy('id')->last()
            : null;
        $submitted = $document->transitions->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted)->sortBy('id')->first();

        return [
            'id' => $document->id,
            ...StudentDocumentLabel::payload($document),
            'formType' => $document->form_type->value,
            'formTypeLabel' => $document->form_type->label(),
            'submittedAt' => ($submitted?->created_at ?? $document->created_at)?->toIso8601String(),
            'status' => $document->status->value,
            'action' => $this->action($document),
            'steps' => $isDraft ? null : $this->steps($document, $returned),
            'note' => $returned === null ? null : $this->note($document, $returned),
        ];
    }

    /**
     * @return array{label: string, href: string, kind: string}
     */
    private function action(Document $document): array
    {
        return match (true) {
            $document->status === DocumentStatus::Returned => [
                'label' => 'Fix and resend',
                'href' => route($document->form_type->studentEditRouteName(), $document),
                'kind' => 'fix',
            ],
            $document->status === DocumentStatus::Draft && $document->form_type === FormType::ActivityProposal => [
                'label' => 'Continue draft',
                'href' => route('activity-proposals.continue', $document),
                'kind' => 'continue',
            ],
            default => [
                'label' => 'View',
                'href' => route($document->form_type->studentShowRouteName(), $document),
                'kind' => 'view',
            ],
        };
    }

    /**
     * Submitted, one node per step of THIS document's chain, then Approved.
     *
     * @return list<array<string, mixed>>
     */
    private function steps(Document $document, ?DocumentTransition $returned): array
    {
        $steps = ($document->workflowTemplate?->steps ?? collect())->sortBy('position')->values();
        $transitions = $document->transitions->sortBy('id')->values();
        $status = $document->status;

        $submitted = $transitions->first(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted);
        $stopped = in_array($status, [DocumentStatus::Rejected], true)
            ? $transitions->last(fn (DocumentTransition $t) => in_array($t->action, [TransitionAction::Rejected, TransitionAction::Withdrawn], true))
            : null;
        $returnedAt = $returned?->step_position ?? $document->current_step_position;
        $stateOf = function (WorkflowStep $step) use ($status, $document, $stopped, $returned, $returnedAt): string {
            return match (true) {
                $stopped !== null && $step->position === $stopped->step_position => 'rejected',
                $stopped !== null && $stopped->step_position !== null && $step->position < $stopped->step_position => 'done',
                $stopped !== null => 'upcoming',
                $status === DocumentStatus::Approved => 'done',
                $returned !== null && $step->position === $returnedAt => 'returned',
                $returned !== null && $returnedAt !== null && $step->position < $returnedAt => 'done',
                $returned !== null => 'upcoming',
                $status === DocumentStatus::InReview && $document->current_step_position !== null && $step->position < $document->current_step_position => 'done',
                $status === DocumentStatus::InReview && $step->position === $document->current_step_position => 'current',
                default => 'upcoming',
            };
        };

        $nodes = [[
            'key' => 'submitted',
            'label' => 'Submitted',
            'state' => $submitted === null ? 'upcoming' : 'done',
            'date' => ($submitted?->created_at)?->toIso8601String(),
            'hereDays' => null,
            'isNext' => false,
            'muted' => false,
            'popover' => $submitted === null ? null : [
                'status' => 'Submitted',
                'roleText' => 'Officer, '.$document->organization->name,
                'receivedAt' => $submitted->created_at?->toIso8601String(),
                'decision' => null,
                'waitingDays' => null,
                'people' => $submitted->actor === null ? [] : [[
                    'name' => $submitted->actor->name,
                    'role' => 'Officer, '.$document->organization->name,
                    'email' => null,
                    'approved' => false,
                ]],
            ],
        ]];

        $previousState = 'done';

        foreach ($steps as $step) {
            $state = $stateOf($step);
            $decided = $transitions->filter(fn (DocumentTransition $t) => $t->step_position === $step->position
                && in_array($t->action, [TransitionAction::Approved, TransitionAction::Completed], true));
            $arrival = $transitions->last(fn (DocumentTransition $t) => $t->step_position === $step->position
                && in_array($t->action, [TransitionAction::Submitted, TransitionAction::Advanced, TransitionAction::Resubmitted], true));

            $date = match ($state) {
                'done' => $decided->last()?->created_at,
                'returned' => $returned?->created_at,
                'rejected' => $stopped?->created_at,
                default => null,
            };

            $nodes[] = [
                'key' => 'step-'.$step->position,
                'label' => self::stepLabel($step->role),
                'state' => $state,
                'date' => $date?->toIso8601String(),
                'hereDays' => $state === 'current' && $arrival?->created_at !== null
                    ? (int) $arrival->created_at->diffInDays(now(), true)
                    : null,
                'isNext' => $state === 'upcoming' && $previousState === 'current',
                'muted' => $stopped !== null && $state === 'upcoming',
                'popover' => $this->popover($document, $step, $state, $decided, $arrival, $state === 'returned' ? $returned : ($state === 'rejected' ? $stopped : null)),
            ];

            $previousState = $state;
        }

        $completed = $transitions->last(fn (DocumentTransition $t) => $t->action === TransitionAction::Completed);
        $nodes[] = [
            'key' => 'approved',
            'label' => 'Approved',
            'state' => $status === DocumentStatus::Approved ? 'done' : 'upcoming',
            'date' => ($status === DocumentStatus::Approved ? $completed?->created_at : null)?->toIso8601String(),
            'hereDays' => null,
            'isNext' => false,
            'muted' => $stopped !== null,
            'popover' => null,
        ];

        return $nodes;
    }

    /**
     * Who has (or had) this step, and when. A step already decided names the
     * people who actually acted, from the transitions (the approvers; whoever
     * returned or rejected it); the current step names whoever holds the seat
     * now, resolved the way the engine and the notifications resolve it. A
     * step still ahead gives only its role and "Waiting": no person, no date.
     * Every date is a document_transitions timestamp.
     *
     * @param  Collection<int, DocumentTransition>  $decided  Approved/Completed transitions at this step
     * @param  DocumentTransition|null  $stoppedBy  The Returned or Rejected transition when this step is the one that stopped
     * @return array{status: string, roleText: string, receivedAt: string|null, decision: array{label: string, at: string|null}|null, waitingDays: int|null, people: list<array<string, mixed>>}
     */
    private function popover(Document $document, WorkflowStep $step, string $state, Collection $decided, ?DocumentTransition $arrival, ?DocumentTransition $stoppedBy): array
    {
        $scope = $this->scopeFor($step->role, $document->organization);
        $roleText = self::stepLabel($step->role).($scope !== null ? ', '.$scope : '');
        $approvedIds = $document->stepApprovals->where('step_position', $step->position)->pluck('user_id');

        $actors = match ($state) {
            'done' => $decided->map(fn (DocumentTransition $t) => $t->actor)->filter()->unique('id')->values(),
            'returned', 'rejected' => collect([$stoppedBy?->actor])->filter()->values(),
            default => collect(),
        };

        $people = match (true) {
            $state === 'upcoming' => collect(),
            $actors->isNotEmpty() => $actors,
            default => $this->holders($step, $document),
        };

        $decision = match ($state) {
            'done' => ['label' => 'Approved', 'at' => $decided->last()?->created_at?->toIso8601String()],
            'returned' => ['label' => 'Returned', 'at' => $stoppedBy?->created_at?->toIso8601String()],
            'rejected' => ['label' => 'Rejected', 'at' => $stoppedBy?->created_at?->toIso8601String()],
            default => null,
        };

        return [
            'status' => match ($state) {
                'current' => 'Reviewing now',
                'done' => 'Approved',
                'returned' => 'Returned',
                'rejected' => 'Rejected',
                default => 'Waiting',
            },
            'roleText' => $roleText,
            'receivedAt' => $state === 'upcoming' ? null : $arrival?->created_at?->toIso8601String(),
            'decision' => $decision,
            'waitingDays' => $state === 'current' && $arrival?->created_at !== null
                ? (int) $arrival->created_at->diffInDays(now(), true)
                : null,
            'people' => $people
                ->map(fn (User $user) => [
                    'name' => $user->name,
                    'role' => $roleText,
                    // Officers already see their adviser's email (My Organization);
                    // no other approver's email is shown to them anywhere.
                    'email' => $step->role === Role::Adviser ? $user->email : null,
                    'approved' => $state === 'current' && $approvedIds->contains($user->id),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The seat's current holders, once per role and organization per request.
     * A seat nobody holds resolves to nobody, never an error.
     *
     * @return Collection<int, User>
     */
    private function holders(WorkflowStep $step, Document $document): Collection
    {
        $key = $step->role->value.':'.$document->organization_id;

        return $this->holderCache[$key] ??= (function () use ($step, $document) {
            try {
                return collect($this->resolver->approversFor($step, $document)->all());
            } catch (Throwable) {
                return collect();
            }
        })();
    }

    /** The seat's scope as a short name: the organization for an adviser, the school's initials for a dean or principal. */
    private function scopeFor(Role $role, Organization $organization): ?string
    {
        return match ($role) {
            Role::Adviser => $organization->name,
            Role::ProgramChair => $organization->program?->name,
            Role::Dean, Role::Principal => $organization->school === null ? null : self::initials($organization->school->name),
            default => null,
        };
    }

    /** "School of Architecture, Computing, and Engineering" -> "SACE"; "Senior High School" -> "SHS". */
    public static function initials(string $name): string
    {
        return collect(preg_split('/[\s,]+/', $name) ?: [])
            ->filter(fn (string $word) => $word !== '' && ctype_upper($word[0]))
            ->map(fn (string $word) => $word[0])
            ->implode('');
    }

    /**
     * The returning approver's note, and any remarks written since.
     *
     * @return array{role: string, comment: string|null, remarks: list<array{name: string, body: string}>}
     */
    private function note(Document $document, DocumentTransition $returned): array
    {
        $step = $this->stepFor($document, $returned->step_position);

        return [
            'role' => $step === null ? 'Approver' : match ($step->role) {
                Role::SdaoMember => 'SDAO',
                Role::Dean => 'Dean',
                default => $step->role->label(),
            },
            'comment' => $returned->comment,
            'remarks' => $document->remarks
                ->filter(fn ($remark) => $remark->created_at >= $returned->created_at)
                ->sortBy('id')
                ->map(fn ($remark) => ['name' => $remark->author?->name ?? 'An approver', 'body' => $remark->body])
                ->values()
                ->all(),
        ];
    }

    private function stepFor(Document $document, ?int $position): ?WorkflowStep
    {
        return $position === null ? null : $document->workflowTemplate?->steps->firstWhere('position', $position);
    }
}
