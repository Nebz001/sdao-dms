<?php

namespace App\Approval;

use App\Approval\Contracts\ApproverNotifier;
use App\Approval\Exceptions\DuplicateApprovalException;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative approval engine (invariants #1–#9).
 *
 * The engine is generic: it walks steps defined in data (WorkflowTemplate /
 * WorkflowStep) and resolves each step's role to person(s) via
 * StepApproverResolver. It has no knowledge of form types, school structure,
 * or specific roles. All domain rules emerge from the generic mechanics:
 *
 * - Reject is terminal: Rejected has no further transition.
 * - Return-to-requester-by-rank: current_step_position holds at R on return;
 *   lower steps' approvals persist; resume re-enters at R and moves forward only.
 * - Dual SDAO (required_approvals = 2): second approval advances; first does not.
 * - A split (one SDAO approves, the other returns): partial clears, both
 *   must re-approve on resume.
 */
class ApprovalEngine
{
    public function __construct(
        private readonly WorkflowTemplateResolver $templateResolver,
        private readonly StepApproverResolver $approverResolver,
        private readonly ApproverNotifier $notifier,
    ) {}

    // -------------------------------------------------------------------------
    // Public transition methods
    // -------------------------------------------------------------------------

    /**
     * Submit a Draft document into the approval chain.
     *
     * @param  User  $actor  The student (president or secretary) submitting the document.
     *
     * @throws InvalidTransitionException
     */
    public function submit(Document $document, User $actor): void
    {
        $this->guardStatus($document, DocumentStatus::Draft, 'submit');

        DB::transaction(function () use ($document, $actor) {
            $template = $this->templateResolver->resolve(
                $document->form_type,
                $document->variant,
            );

            $fromStatus = $document->status;

            $document->workflow_template_id = $template->id;
            $document->status = DocumentStatus::InReview;
            $document->current_step_position = 1;
            $document->save();

            $this->recordTransition($document, $actor, TransitionAction::Submitted, $fromStatus, DocumentStatus::InReview, 1);
            $this->activateStep($document, 1);
        });
    }

    /**
     * Record an approval from the given user at the document's current step.
     *
     * If this approval satisfies the quorum (required_approvals), the document
     * advances to the next step (or becomes Approved if it was the final step).
     *
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     * @throws DuplicateApprovalException
     */
    public function approve(Document $document, User $actor): void
    {
        $this->guardStatus($document, DocumentStatus::InReview, 'approve');

        DB::transaction(function () use ($document, $actor) {
            $step = $this->resolveCurrentStep($document);
            $approvers = $this->approverResolver->approversFor($step, $document);

            $this->guardIsApprover($actor, $approvers);
            $this->guardNoDuplicate($document, $step, $actor);

            // Record this individual approval.
            DocumentStepApproval::create([
                'document_id' => $document->id,
                'workflow_step_id' => $step->id,
                'step_position' => $step->position,
                'user_id' => $actor->id,
            ]);

            $this->recordTransition($document, $actor, TransitionAction::Approved, $document->status, $document->status, $step->position);

            // Check quorum.
            $approvalCount = DocumentStepApproval::query()
                ->where('document_id', $document->id)
                ->where('workflow_step_id', $step->id)
                ->count();

            if ($approvalCount < $step->required_approvals) {
                // Quorum not yet reached (e.g. first of two SDAO members).
                return;
            }

            // Quorum reached — advance or complete.
            $template = $document->workflowTemplate()->with('steps')->first();
            $maxPosition = $template->steps->max('position');

            if ($step->position >= $maxPosition) {
                // Final step: document fully approved.
                $fromStatus = $document->status;
                $document->status = DocumentStatus::Approved;
                $document->current_step_position = null;
                $document->save();

                $this->recordTransition($document, $actor, TransitionAction::Completed, $fromStatus, DocumentStatus::Approved, $step->position);
            } else {
                // Advance to the next step.
                $fromStatus = $document->status;
                $nextPosition = $step->position + 1;
                $document->current_step_position = $nextPosition;
                $document->save();

                $this->recordTransition($document, $actor, TransitionAction::Advanced, $fromStatus, DocumentStatus::InReview, $nextPosition);
                $this->activateStep($document, $nextPosition);
            }
        });
    }

    /**
     * Permanently reject the document. No further transitions are possible.
     *
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     */
    public function reject(Document $document, User $actor, ?string $comment = null): void
    {
        $this->guardStatus($document, DocumentStatus::InReview, 'reject');

        DB::transaction(function () use ($document, $actor, $comment) {
            $step = $this->resolveCurrentStep($document);
            $approvers = $this->approverResolver->approversFor($step, $document);

            $this->guardIsApprover($actor, $approvers);

            $fromStatus = $document->status;
            $document->status = DocumentStatus::Rejected;
            $document->current_step_position = null;
            $document->save();

            $this->recordTransition($document, $actor, TransitionAction::Rejected, $fromStatus, DocumentStatus::Rejected, $step->position, $comment);
            // No notification: terminal action (invariant #2).
        });
    }

    /**
     * Return the document to the student for revision.
     *
     * The document resumes at THIS approver's step (not from the beginning) when
     * the student resubmits. Approvals below this step are preserved; only the
     * current step's partials are cleared (invariant #2).
     *
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     */
    public function returnForRevision(Document $document, User $actor, ?string $comment = null): void
    {
        $this->guardStatus($document, DocumentStatus::InReview, 'return');

        DB::transaction(function () use ($document, $actor, $comment) {
            $step = $this->resolveCurrentStep($document);
            $approvers = $this->approverResolver->approversFor($step, $document);

            $this->guardIsApprover($actor, $approvers);

            // Clear only the current step's partial approvals so that on resume
            // the step is decided fresh. Lower steps' approvals are untouched.
            DocumentStepApproval::query()
                ->where('document_id', $document->id)
                ->where('workflow_step_id', $step->id)
                ->delete();

            $fromStatus = $document->status;

            // current_step_position stays at the returning step — this is the
            // resume point when the student resubmits (invariant #2).
            $document->status = DocumentStatus::Returned;
            $document->save();

            $this->recordTransition($document, $actor, TransitionAction::Returned, $fromStatus, DocumentStatus::Returned, $step->position, $comment);
        });
    }

    /**
     * Resubmit a Returned document.
     *
     * Resumes at the step that issued the return (current_step_position is
     * unchanged). Approvals for all earlier steps still count (invariant #2).
     *
     * @param  User  $actor  The student resubmitting after revision (president or secretary).
     *
     * @throws InvalidTransitionException
     */
    public function resubmit(Document $document, User $actor): void
    {
        $this->guardStatus($document, DocumentStatus::Returned, 'resubmit');

        DB::transaction(function () use ($document, $actor) {
            $fromStatus = $document->status;
            $resumePosition = $document->current_step_position;

            $document->status = DocumentStatus::InReview;
            $document->save();

            $this->recordTransition($document, $actor, TransitionAction::Resubmitted, $fromStatus, DocumentStatus::InReview, $resumePosition);
            $this->activateStep($document, $resumePosition);
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fire notifications to every approver who must act at the given step.
     * This is the single hand-off point for invariant #9.
     */
    private function activateStep(Document $document, int $position): void
    {
        $step = $this->resolveStep($document, $position);
        $approvers = $this->approverResolver->approversFor($step, $document);

        foreach ($approvers as $approver) {
            $this->notifier->notify($approver, $document, $position);
        }
    }

    private function resolveCurrentStep(Document $document): WorkflowStep
    {
        return $this->resolveStep($document, $document->current_step_position);
    }

    private function resolveStep(Document $document, int $position): WorkflowStep
    {
        return WorkflowStep::query()
            ->where('workflow_template_id', $document->workflow_template_id)
            ->where('position', $position)
            ->firstOrFail();
    }

    /**
     * @param  iterable<int, User>  $approvers
     *
     * @throws UnauthorizedApproverException
     */
    private function guardIsApprover(User $actor, iterable $approvers): void
    {
        foreach ($approvers as $approver) {
            if ($approver->id === $actor->id) {
                return;
            }
        }

        throw new UnauthorizedApproverException(
            "User [{$actor->id}] is not an approver for this step."
        );
    }

    /** @throws DuplicateApprovalException */
    private function guardNoDuplicate(Document $document, WorkflowStep $step, User $actor): void
    {
        $exists = DocumentStepApproval::query()
            ->where('document_id', $document->id)
            ->where('workflow_step_id', $step->id)
            ->where('user_id', $actor->id)
            ->exists();

        if ($exists) {
            throw new DuplicateApprovalException(
                "User [{$actor->id}] has already approved step [{$step->position}]."
            );
        }
    }

    /** @throws InvalidTransitionException */
    private function guardStatus(Document $document, DocumentStatus $required, string $action): void
    {
        if ($document->status !== $required) {
            throw new InvalidTransitionException(
                "Cannot {$action} a document with status [{$document->status->value}]. Expected [{$required->value}]."
            );
        }
    }

    private function recordTransition(
        Document $document,
        ?User $actor,
        TransitionAction $action,
        ?DocumentStatus $fromStatus,
        DocumentStatus $toStatus,
        ?int $stepPosition = null,
        ?string $comment = null,
    ): void {
        DocumentTransition::create([
            'document_id' => $document->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'step_position' => $stepPosition,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }
}
