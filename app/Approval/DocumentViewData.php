<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Http\Requests\StoreDocumentRemarkRequest;
use App\Models\Document;
use App\Models\DocumentRemark;
use App\Models\DocumentTransition;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Everything the shared document page (resources/js/components/document-view)
 * needs that is NOT form-specific: header facts, the unified history timeline,
 * the approval flow, the record card and the "waiting on" banner data. Each
 * show action passes its own subject and meta chips and spreads the result
 * into one `view` prop, so no form type forks its own copy of this logic.
 *
 * Read-only over the document and its transitions; nothing here writes.
 */
class DocumentViewData
{
    public function __construct(private readonly StepApproverResolver $resolver) {}

    /**
     * @param  list<array{label: string, value: string|null}>  $chips  Meta chips under the title; empty values are dropped. "Submitted" is always appended.
     * @return array<string, mixed>
     */
    public function for(Document $document, User $viewer, string $subject, array $chips): array
    {
        $document->loadMissing([
            'organization',
            'submitter:id,name',
            'transitions.actor',
            'remarks.author',
            'stepApprovals.user',
            'workflowTemplate.steps',
        ]);

        $steps = $this->steps($document);
        $transitions = $document->transitions;
        $submittedAt = $transitions->firstWhere('action', TransitionAction::Submitted)?->created_at ?? $document->created_at;

        return [
            'typeLabel' => match ($document->form_type) {
                FormType::OrganizationRegistration => 'Registration',
                FormType::OrganizationRenewal => 'Renewal',
                FormType::ActivityCalendar => 'Calendar',
                FormType::ActivityProposal => 'Proposal',
                FormType::AfterActivityReport => 'Report',
            },
            'subject' => $subject,
            'submittedAt' => $submittedAt?->toIso8601String(),
            'chips' => collect([...$chips, ['label' => 'Submitted', 'value' => $submittedAt?->format('n/j/Y')]])
                ->filter(fn (array $chip) => filled($chip['value'] ?? null))
                ->values()
                ->all(),
            'history' => $this->history($document, $steps),
            'remark' => [
                // The `remark` ability, not `view`/`review`: the box is only
                // offered to someone the POST would accept.
                'canAdd' => Gate::forUser($viewer)->allows('remark', $document),
                'url' => route('documents.remarks.store', $document, absolute: false),
                'maxLength' => StoreDocumentRemarkRequest::MAX_LENGTH,
            ],
            'flow' => $this->flow($document, $viewer, $steps),
            'waiting' => $this->waiting($document, $steps),
            'quorum' => $this->quorum($document, $steps),
            'record' => $this->record($document, $submittedAt),
        ];
    }

    /**
     * @return Collection<int, WorkflowStep>
     */
    private function steps(Document $document): Collection
    {
        return ($document->workflowTemplate?->steps ?? collect())->sortBy('position')->values();
    }

    /**
     * Newest first. Actors carry a role label: the step's role for a decision,
     * the officer position for a student's submit/resubmit.
     *
     * @param  Collection<int, WorkflowStep>  $steps
     * @return list<array<string, mixed>>
     */
    private function history(Document $document, Collection $steps): array
    {
        $positions = OrganizationMembership::query()
            ->where('organization_id', $document->organization_id)
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('id')->first()->position->label());

        $labels = SectionFlags::labelsFor($document->form_type);
        // Activity calendars have no static section registry; their keys are
        // positional ("activity_0"), shown as "Activity 1".
        $label = fn (string $key): string => $labels[$key]
            ?? (preg_match('/^activity_(\d+)$/', $key, $m) ? 'Activity '.((int) $m[1] + 1) : $key);

        $transitions = $document->transitions
            ->sortByDesc('id')
            ->map(function (DocumentTransition $t) use ($steps, $positions, $label) {
                $isDecision = in_array($t->action, [TransitionAction::Approved, TransitionAction::Completed, TransitionAction::Returned, TransitionAction::Rejected], true);
                $role = $isDecision
                    ? $steps->firstWhere('position', $t->step_position)?->role->label()
                    : ($t->actor_id ? $positions->get($t->actor_id) : null);

                return [
                    'key' => 'transition-'.$t->id,
                    'kind' => 'transition',
                    'id' => $t->id,
                    'action' => $t->action->value,
                    'step_position' => $t->step_position,
                    'comment' => $t->comment,
                    'flagged' => array_map($label, $t->flagged_sections ?? []),
                    'section_notes' => collect(SectionFlags::cleanNotes($t->section_comments))
                        ->map(fn (string $note, string $key) => ['label' => $label($key), 'note' => $note])
                        ->values()
                        ->all(),
                    'field_changes' => $t->field_changes,
                    'actor' => $t->actor ? ['name' => $t->actor->name, 'role' => $role] : null,
                    'created_at' => $t->created_at?->toIso8601String(),
                ];
            })
            ->values();

        $remarks = $document->remarks
            ->sortByDesc('id')
            ->map(fn (DocumentRemark $r) => [
                'key' => 'remark-'.$r->id,
                'kind' => 'remark',
                'id' => $r->id,
                'action' => 'remark',
                'step_position' => null,
                'comment' => $r->body,
                'flagged' => [],
                'section_notes' => [],
                'field_changes' => null,
                'actor' => $r->author ? ['name' => $this->authorName($r->author), 'role' => $this->remarkAuthorRole($r->author, $steps, $document)] : null,
                'created_at' => $r->created_at->toIso8601String(),
            ])
            ->values();

        return $this->mergeNewestFirst($transitions, $remarks);
    }

    /**
     * Interleaves the two newest-first lists by time without disturbing the
     * order inside either one (transitions keep their id order, so nothing
     * about an existing timeline moves). On an exact tie the remark goes
     * first: a remark can only be written after the event it comments on,
     * and SQLite stores seconds while Postgres keeps microseconds, so ties
     * must resolve the same way on both.
     *
     * @param  Collection<int, array<string, mixed>>  $transitions
     * @param  Collection<int, array<string, mixed>>  $remarks
     * @return list<array<string, mixed>>
     */
    private function mergeNewestFirst(Collection $transitions, Collection $remarks): array
    {
        $merged = [];
        $t = 0;
        $r = 0;

        while ($t < $transitions->count() || $r < $remarks->count()) {
            $transition = $transitions[$t] ?? null;
            $remark = $remarks[$r] ?? null;

            $takeRemark = $transition === null
                || ($remark !== null && $this->instant($remark) >= $this->instant($transition));

            if ($takeRemark) {
                $merged[] = $remark;
                $r++;
            } else {
                $merged[] = $transition;
                $t++;
            }
        }

        return $merged;
    }

    /** @param  array<string, mixed>  $event */
    private function instant(array $event): int
    {
        return $event['created_at'] === null ? 0 : (int) strtotime((string) $event['created_at']);
    }

    /** "First Last" without an honorific, falling back to the stored display name. */
    private function authorName(User $author): string
    {
        return PersonName::join($author->first_name, $author->last_name) ?: $author->name;
    }

    /**
     * The role a remark's author holds on THIS document: the step they
     * approve, else their first assigned role (an SDAO member remarking from
     * outside their step).
     *
     * @param  Collection<int, WorkflowStep>  $steps
     */
    private function remarkAuthorRole(User $author, Collection $steps, Document $document): ?string
    {
        foreach ($steps as $step) {
            if ($this->approvers($step, $document)->contains('id', $author->id)) {
                return $step->role->label();
            }
        }

        return $author->roleAssignments()->first()?->role->label();
    }

    /**
     * Submitted, one node per configured step, then Completed. "Step N of M"
     * counts those nodes, so a short chain reads "Step 2 of 3".
     *
     * @param  Collection<int, WorkflowStep>  $steps
     * @return list<array<string, mixed>>
     */
    private function flow(Document $document, User $viewer, Collection $steps): array
    {
        $transitions = $document->transitions;
        $submitted = $transitions->firstWhere('action', TransitionAction::Submitted);
        $status = $document->status;
        $current = $document->current_step_position;

        $nodes = [[
            'key' => 'submitted',
            'name' => 'Submitted',
            'actors' => array_values(array_filter([$document->submitter?->name ?? $submitted?->actor?->name])),
            'date' => ($submitted?->created_at)?->toIso8601String(),
            'state' => $submitted === null ? 'upcoming' : 'done',
            'isYou' => false,
        ]];

        foreach ($steps as $step) {
            $decided = $transitions
                ->filter(fn (DocumentTransition $t) => $t->step_position === $step->position
                    && in_array($t->action, [TransitionAction::Approved, TransitionAction::Completed], true))
                ->sortBy('id');
            $rejected = $status === DocumentStatus::Rejected
                ? $transitions->first(fn (DocumentTransition $t) => $t->action === TransitionAction::Rejected && $t->step_position === $step->position)
                : null;

            $state = match (true) {
                $rejected !== null => 'rejected',
                $status === DocumentStatus::Approved => 'done',
                $current !== null && $step->position < $current => 'done',
                $current !== null && $step->position === $current && $status !== DocumentStatus::Draft => 'current',
                default => 'upcoming',
            };

            $approvers = $this->approvers($step, $document);
            $actors = $decided->isNotEmpty() && $state === 'done'
                ? $decided->map(fn (DocumentTransition $t) => $t->actor?->name)->filter()->unique()->values()->all()
                : $approvers->pluck('name')->all();

            $nodes[] = [
                'key' => 'step-'.$step->position,
                'name' => $this->stepName($step),
                'actors' => $actors,
                'date' => ($rejected?->created_at ?? $decided->last()?->created_at)?->toIso8601String(),
                'state' => $state,
                'isYou' => $state === 'current' && $approvers->contains('id', $viewer->id),
            ];
        }

        $completed = $transitions->firstWhere('action', TransitionAction::Completed);
        $nodes[] = [
            'key' => 'completed',
            'name' => 'Completed',
            'actors' => array_values(array_filter([$completed?->actor?->name])),
            'date' => ($completed?->created_at)?->toIso8601String(),
            'state' => $status === DocumentStatus::Approved ? 'done' : 'upcoming',
            'isYou' => false,
        ];

        return $nodes;
    }

    /** "SDAO review", "Adviser review", "Dean review". */
    private function stepName(WorkflowStep $step): string
    {
        $role = $step->role === Role::SdaoMember ? 'SDAO' : $step->role->label();

        return $role.' review';
    }

    /**
     * A role with no holder yet (for example, no adviser set) resolves to an
     * empty list, never an exception.
     *
     * @return Collection<int, User>
     */
    private function approvers(WorkflowStep $step, Document $document): Collection
    {
        try {
            return collect($this->resolver->approversFor($step, $document)->all());
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @param  Collection<int, WorkflowStep>  $steps
     * @return array{step: int, totalSteps: int, stepName: string, days: int}|null
     */
    private function waiting(Document $document, Collection $steps): ?array
    {
        if ($document->status !== DocumentStatus::InReview || $document->current_step_position === null) {
            return null;
        }

        $step = $steps->firstWhere('position', $document->current_step_position);

        if ($step === null) {
            return null;
        }

        $since = $document->transitions
            ->whereIn('action', [TransitionAction::Submitted, TransitionAction::Resubmitted, TransitionAction::Advanced])
            ->last()?->created_at ?? $document->created_at;

        return [
            // Submitted is node 1, so the first configured step is node 2.
            'step' => $steps->search(fn (WorkflowStep $s) => $s->position === $step->position) + 2,
            'totalSteps' => $steps->count() + 2,
            'stepName' => $this->stepName($step),
            'days' => (int) $since->diffInDays(now(), true),
        ];
    }

    /**
     * Only steps that need more than one approver (SDAO) report a quorum.
     *
     * @param  Collection<int, WorkflowStep>  $steps
     * @return array{required: int, approvedBy: list<string>}|null
     */
    private function quorum(Document $document, Collection $steps): ?array
    {
        if ($document->status !== DocumentStatus::InReview) {
            return null;
        }

        $step = $steps->firstWhere('position', $document->current_step_position);

        if ($step === null || $step->required_approvals < 2) {
            return null;
        }

        return [
            'required' => $step->required_approvals,
            'approvedBy' => $document->stepApprovals
                ->where('step_position', $step->position)
                ->map(fn ($a) => $a->user->name)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Document $document, mixed $submittedAt): array
    {
        $transitions = $document->transitions;
        $decision = $document->status->isTerminal()
            ? $transitions->last(fn (DocumentTransition $t) => in_array($t->action, [TransitionAction::Completed, TransitionAction::Rejected, TransitionAction::Withdrawn], true))
            : null;

        $days = $decision && $submittedAt ? (int) $submittedAt->diffInDays($decision->created_at, true) : null;

        return [
            'submittedBy' => $document->submitter?->name,
            'decidedBy' => $decision?->actor?->name,
            'decidedOn' => $decision?->created_at?->toIso8601String(),
            'revisions' => $transitions->where('action', TransitionAction::Resubmitted)->count(),
            'timeToDecide' => $days,
        ];
    }
}
