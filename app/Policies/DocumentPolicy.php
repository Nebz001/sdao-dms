<?php

namespace App\Policies;

use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Organizations\OrganizationMembershipService;

class DocumentPolicy
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly StepApproverResolver $approverResolver,
    ) {}

    /**
     * Can the user submit a new document for this org?
     */
    public function submit(User $user, Organization $organization): bool
    {
        return $this->membershipService->activeMembershipFor($user, $organization) !== null;
    }

    /**
     * Can the user edit this document? (Only when Returned, only the original submitter.)
     */
    public function edit(User $user, Document $document): bool
    {
        return $document->status === DocumentStatus::Returned
            && $document->submitted_by === $user->id;
    }

    /**
     * Can the user review (approve/reject/return) this document?
     *
     * Generalised to "actor ∈ current-step approvers" so that the long
     * proposal chains route correctly. For short chains (registration,
     * calendar) the current step is always the SDAO step, so the behavior
     * is identical to the old SDAO-membership check.
     */
    public function review(User $user, Document $document): bool
    {
        if ($document->workflow_template_id === null || $document->current_step_position === null) {
            return false;
        }

        $step = WorkflowStep::query()
            ->where('workflow_template_id', $document->workflow_template_id)
            ->where('position', $document->current_step_position)
            ->first();

        if ($step === null) {
            return false;
        }

        return $this->approverResolver->approversFor($step, $document)->contains('id', $user->id);
    }
}
