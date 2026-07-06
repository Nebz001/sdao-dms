<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;

class RegistrationPolicy
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * Can the user submit a new registration for this org?
     */
    public function submit(User $user, Organization $organization): bool
    {
        return $this->membershipService->activeMembershipFor($user, $organization) !== null;
    }

    /**
     * Can the user edit this registration? (Only when Returned, only the original submitter.)
     */
    public function edit(User $user, Document $document): bool
    {
        return $document->status === DocumentStatus::Returned
            && $document->submitted_by === $user->id;
    }

    /**
     * Can the user review (approve/reject/return) this registration?
     */
    public function review(User $user, Document $document): bool
    {
        return $this->roleDirectory->sdaoMembers()->contains('id', $user->id);
    }
}
