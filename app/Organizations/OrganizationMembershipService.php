<?php

namespace App\Organizations;

use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
     * Both active officers (President and Secretary — equal partners, per
     * CLAUDE.md) of the given org. Used to fan out document-outcome
     * notifications and, via canActOnDocument(), to widen document
     * edit/resubmit authorization beyond the literal submitter.
     *
     * @return Collection<int, User>
     */
    public function activeOfficersFor(Organization $organization): Collection
    {
        return User::query()
            ->whereHas('organizationMemberships', fn ($q) => $q
                ->where('organization_id', $organization->id)
                ->where('is_active', true))
            ->get();
    }

    /**
     * Can this user act on (view/edit/resubmit) this document as an officer
     * of its org? True for any active officer (President OR Secretary — the
     * only two OfficerPosition cases, so "active membership" already IS
     * "president or secretary") of the document's organization, OR the
     * document's original submitter.
     *
     * The submitted_by clause is NOT redundant with membership: a founding
     * student proposing a brand-new organization has no OrganizationMembership
     * row yet on their own pending registration (that binding only happens at
     * SDAO approval — see ApproveOrganizationRegistration) — see
     * DocumentPolicy::view()'s docblock for the same edge case.
     */
    public function canActOnDocument(User $user, Document $document): bool
    {
        return $document->submitted_by === $user->id
            || $this->activeMembershipFor($user, $document->organization) !== null;
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
