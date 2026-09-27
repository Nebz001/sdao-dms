<?php

namespace App\Http\Resources\Mobile;

use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use App\Models\Document;

/**
 * Single source of truth for status/stage/step/total/approval mapping —
 * built once per document and shared by the queue summary and the full
 * detail resource, so the two can never disagree about the same document.
 *
 * Expects $document->workflowTemplate.steps, ->transitions and
 * ->stepApprovals already eager-loaded; falls back to empty collections
 * otherwise (never issues its own query).
 */
class ProposalProgress
{
    public readonly string $status;

    public readonly string $currentStage;

    public readonly string $currentStageLabel;

    public readonly int $currentStep;

    public readonly int $totalSteps;

    public readonly string $approvalStageLabel;

    public readonly int $approvedCount;

    public readonly int $requiredCount;

    public function __construct(Document $document)
    {
        $steps = $document->workflowTemplate?->steps ?? collect();
        $this->totalSteps = $steps->count();

        $this->status = match ($document->status) {
            DocumentStatus::Draft => 'pending',
            DocumentStatus::InReview => 'in_review',
            DocumentStatus::Returned => 'revision_requested',
            DocumentStatus::Approved => 'approved',
            DocumentStatus::Rejected => 'rejected',
        };

        $currentWorkflowStep = $document->current_step_position !== null
            ? $steps->firstWhere('position', $document->current_step_position)
            : null;

        if (
            in_array($document->status, [DocumentStatus::InReview, DocumentStatus::Returned], true)
            && $currentWorkflowStep !== null
        ) {
            [$this->currentStage, $this->currentStageLabel] = ProposalStage::forRole($currentWorkflowStep->role);
            $this->currentStep = $document->current_step_position;
            $this->approvedCount = $document->stepApprovals
                ->where('step_position', $currentWorkflowStep->position)
                ->count();
            $this->requiredCount = $currentWorkflowStep->required_approvals;
            // The approval block's own label (e.g. "SDAO Member Approval")
            // is the ROLE's label + " Approval" — a different string from
            // the stage label above ("SDAO Review"), so it is NOT reused.
            $this->approvalStageLabel = $currentWorkflowStep->role->label().' Approval';

            return;
        }

        if ($document->status === DocumentStatus::Approved) {
            $this->currentStage = 'completed';
            $this->currentStageLabel = 'Completed';
            $this->currentStep = $this->totalSteps;
            $this->approvedCount = 0;
            $this->requiredCount = 0;
            $this->approvalStageLabel = 'Completed';

            return;
        }

        if ($document->status === DocumentStatus::Rejected) {
            $rejectTransition = $document->transitions->first(
                fn ($t) => $t->action === TransitionAction::Rejected,
            );

            $this->currentStage = 'rejected';
            $this->currentStageLabel = 'Rejected';
            $this->currentStep = $rejectTransition?->step_position ?? 0;
            $this->approvedCount = 0;
            $this->requiredCount = 0;
            $this->approvalStageLabel = 'Rejected';

            return;
        }

        // Draft, or InReview/Returned with a chain the template can't
        // resolve a step for (a misconfigured chain — deny gracefully
        // rather than let a null step blow up formatting).
        $this->currentStage = 'submitted';
        $this->currentStageLabel = 'Submitted';
        $this->currentStep = 0;
        $this->approvedCount = 0;
        $this->requiredCount = 0;
        $this->approvalStageLabel = 'Submitted';
    }
}
