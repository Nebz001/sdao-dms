<?php

namespace App\Policies;

use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Identity\RoleDirectory;
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
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * Can the user submit a new document for this org?
     */
    public function submit(User $user, Organization $organization): bool
    {
        return $this->membershipService->activeMembershipFor($user, $organization) !== null;
    }

    /**
     * Can the user view this document? Either an affiliated officer of the
     * document's own organization, or an approver whose current step in
     * this document's chain is active right now (same check as `review()`
     * — reused, not duplicated). Prevents any authenticated user from
     * reading another organization's document by guessing/enumerating IDs.
     */
    public function view(User $user, Document $document): bool
    {
        return $this->membershipService->activeMembershipFor($user, $document->organization) !== null
            || $this->review($user, $document);
    }

    /**
     * Can the user manage (bind/deactivate) officers for this organization?
     * The org's adviser only — the same check BindOrganizationOfficer
     * performs before binding, reused (not duplicated) here for the
     * deactivation path via RoleDirectory::isAdviserOf().
     */
    public function manageOfficers(User $user, Organization $organization): bool
    {
        return $this->roleDirectory->isAdviserOf($user, $organization);
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
