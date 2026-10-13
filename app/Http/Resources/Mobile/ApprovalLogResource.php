<?php

namespace App\Http\Resources\Mobile;

use App\ActivityProposals\ProposalReference;
use App\Enums\TransitionAction;
use App\Models\DocumentTransition;
use App\Models\WorkflowStep;
use App\Support\ApiTimestamp;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One mobile-origin approver decision. The engine may append an Advanced or
 * Completed transition immediately after Approved; that companion row is
 * folded into this resource so one tap in the app is always one log entry.
 *
 * @property-read DocumentTransition $resource
 */
class ApprovalLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $transition = $this->resource;
        $document = $transition->document;
        $steps = $document->workflowTemplate->steps;
        $step = $steps->firstWhere('position', $transition->step_position);
        $fromStage = $this->stageForStep($step);
        $outcome = $this->approvalOutcome();

        return [
            'id' => "log-{$transition->id}",
            'proposal_reference' => ProposalReference::format($document),
            'proposal_title' => $document->activityProposal->title,
            'action' => $transition->action->value,
            'approver' => [
                'id' => $transition->actor_id,
                'name' => $transition->actor->name,
                // The workflow-step role is the historical role relevant to
                // this decision, even if the user's live assignments change.
                'role' => $step?->role->label() ?? 'Unknown',
            ],
            'from_status' => $transition->from_status?->value,
            'to_status' => ($outcome ?? $transition)->to_status->value,
            'from_stage' => $fromStage,
            'to_stage' => $this->toStage($fromStage, $outcome),
            'remarks' => $transition->comment,
            'created_at' => ApiTimestamp::format($transition->created_at),
        ];
    }

    private function approvalOutcome(): ?DocumentTransition
    {
        if ($this->resource->action !== TransitionAction::Approved) {
            return null;
        }

        // Only the immediate next transition on this document can belong to
        // the same approval. Stopping at that row prevents a partial quorum
        // approval from being paired with another approver's later advance.
        $next = $this->resource->document->transitions
            ->first(fn (DocumentTransition $candidate) => $candidate->id > $this->resource->id);

        if (
            $next === null
            || $next->actor_id !== $this->resource->actor_id
            || $next->source !== $this->resource->source
            || ! in_array($next->action, [TransitionAction::Advanced, TransitionAction::Completed], true)
        ) {
            return null;
        }

        return $next;
    }

    private function toStage(string $fromStage, ?DocumentTransition $outcome): string
    {
        if ($this->resource->action === TransitionAction::Returned) {
            // A return changes status but deliberately resumes at this same
            // approver's workflow stage after the student resubmits.
            return $fromStage;
        }

        if ($this->resource->action === TransitionAction::Rejected) {
            return 'rejected';
        }

        if ($outcome?->action === TransitionAction::Completed) {
            return 'completed';
        }

        if ($outcome?->action === TransitionAction::Advanced) {
            $nextStep = $this->resource->document->workflowTemplate?->steps
                ->firstWhere('position', $outcome->step_position);

            return $this->stageForStep($nextStep);
        }

        // Partial quorum approval: the document is still at the same stage.
        return $fromStage;
    }

    private function stageForStep(?WorkflowStep $step): string
    {
        return $step === null ? 'unknown' : ProposalStage::forRole($step->role)[0];
    }
}
