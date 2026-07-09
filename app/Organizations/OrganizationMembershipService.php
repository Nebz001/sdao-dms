<?php

namespace App\Organizations;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Single source for "can this student submit for this org." Every form-type
 * store()/submit() action and DocumentPolicy::submit()/view() route through
 * this one method — do not duplicate the check elsewhere.
 */
class OrganizationMembershipService
{
    /**
     * Returns the active membership for the given student in the given org,
     * or null if none exists OR the account is not SDAO-Verified. A student
     * can only ever be bound while Verified (see BindOrganizationOfficer), so
     * this is defense-in-depth: it holds even if that invariant is ever
     * violated elsewhere.
     */
    public function activeMembershipFor(User $user, Organization $organization): ?OrganizationMembership
    {
        if (! $user->isVerifiedAccount()) {
            return null;
        }

        return OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * One organization per student (Phase 2 item 4): is this student actively
     * bound as an officer of any organization OTHER than $excluding? Shared by
     * BindOrganizationOfficer (turnover) and, since Phase 2 item 5, the
     * proposal-gate check for a brand-new founding registration — pass null
     * for $excluding when there is no existing organization to exclude yet
     * (checks for ANY active membership at all).
     */
    public function hasActiveMembershipElsewhere(User $student, ?Organization $excluding = null): bool
    {
        return OrganizationMembership::query()
            ->where('user_id', $student->id)
            ->when($excluding !== null, fn ($query) => $query->where('organization_id', '!=', $excluding->id))
            ->active()
            ->exists();
    }
}
