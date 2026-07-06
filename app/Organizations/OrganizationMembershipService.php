<?php

namespace App\Organizations;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Single source for "can this student submit for this org."
 */
class OrganizationMembershipService
{
    /**
     * Returns the active membership for the given student in the given org,
     * or null if none exists.
     */
    public function activeMembershipFor(User $user, Organization $organization): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();
    }
}
