<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;
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

        return $document->transitions
            ->sortByDesc('id')
            ->map(function (DocumentTransition $t) use ($steps, $positions, $label) {
                $isDecision = in_array($t->action, [TransitionAction::Approved, TransitionAction::Completed, TransitionAction::Returned, TransitionAction::Rejected], true);
                $role = $isDecision
                    ? $steps->firstWhere('position', $t->step_position)?->role->label()
                    : ($t->actor_id ? $positions->get($t->actor_id) : null);

                return [
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
            ->values()
            ->all();
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
