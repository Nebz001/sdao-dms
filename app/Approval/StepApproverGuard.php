<?php

namespace App\Approval;

use App\Approval\Exceptions\NoApproverForStepException;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Models\Document;
use App\Models\Organization;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The one check that a step about to receive a document has someone who can
 * act on it. Every way a document can enter a step goes through here: submit,
 * resubmit (which resumes the step that returned it) and an approval that
 * would complete its step and move the document on.
 *
 * It runs BEFORE anything is saved, so a refused action leaves no document,
 * attachment, transition, approval row or notification behind. A misconfigured
 * chain (a role that cannot exist for this organization's shape) is still a
 * LogicException — that is a bug, not a missing person.
 *
 * @throws NoApproverForStepException
 */
class StepApproverGuard
{
    public function __construct(
        private readonly WorkflowTemplateResolver $templateResolver,
        private readonly StepApproverResolver $approverResolver,
    ) {}

    /**
     * The first step of the chain a new document of this form would follow.
     * $organization may be null only when that step does not depend on one
     * (the SDAO-only chains), as for a founding registration.
     */
    public function assertCanSubmit(FormType $formType, ?ProposalVariant $variant, ?Organization $organization): void
    {
        $template = $this->templateResolver->resolve($formType, $variant);

        $this->assertStepHasApprovers($template->steps->sortBy('position')->first(), $organization, submitter: true);
    }

    /** The step this returned document resumes at. */
    public function assertCanResubmit(Document $document): void
    {
        $step = $this->stepAt($document, $document->current_step_position);

        if ($step !== null) {
            $this->assertStepHasApprovers($step, $document->organization, submitter: true);
        }
    }

    /**
     * Only when this approval would complete $step and send the document on:
     * a partial quorum (the first of two SDAO members) moves nothing.
     */
    public function assertCanApprove(Document $document, WorkflowStep $step, int $approvalsAfterThis): void
    {
        if ($approvalsAfterThis < $step->required_approvals) {
            return;
        }

        $next = $document->workflowTemplate()->with('steps')->first()
            ?->steps->where('position', '>', $step->position)->sortBy('position')->first();

        if ($next !== null) {
            $this->assertStepHasApprovers($next, $document->organization, submitter: false);
        }
    }

    private function stepAt(Document $document, ?int $position): ?WorkflowStep
    {
        if ($position === null || $document->workflow_template_id === null) {
            return null;
        }

        return WorkflowStep::query()
            ->where('workflow_template_id', $document->workflow_template_id)
            ->where('position', $position)
            ->first();
    }

    private function assertStepHasApprovers(?WorkflowStep $step, ?Organization $organization, bool $submitter): void
    {
        if ($step === null) {
            return;
        }

        try {
            $holders = $this->approverResolver->approversForOrganization($step, $organization);
        } catch (ModelNotFoundException) {
            throw $this->missing($step, $submitter);
        }

        if ($holders->count() < $step->required_approvals) {
            throw $this->missing($step, $submitter);
        }
    }

    private function missing(WorkflowStep $step, bool $submitter): NoApproverForStepException
    {
        return $submitter
            ? NoApproverForStepException::forSubmitter($step->role)
            : NoApproverForStepException::forApprover($step->role);
    }
}
