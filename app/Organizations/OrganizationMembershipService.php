<?php

namespace App\Organizations;

use App\Enums\OfficerPosition;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonInterface;
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
     * The caller's own active membership, with organization + school eager
     * loaded — the "which org is mine" lookup used to populate the sidebar's
     * auth.organization shared prop (HandleInertiaRequests) and duplicated
     * inline before this at MyOrganizationController::show() and
     * DashboardController::index(). Deliberately does NOT gate on
     * isVerifiedAccount() like activeMembershipFor() does — this mirrors
     * today's `organizationMemberships()->active()->exists()` used for
     * auth.isActiveOfficer exactly, just with eager loading added, so
     * adding that guard here would silently change nav behavior for a case
     * it was never meant to touch.
     */
    public function activeMembershipWithOrganizationFor(User $user): ?OrganizationMembership
    {
        return $user->organizationMemberships()->active()->with('organization.school')->first();
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
     * Active officers across MANY organizations at once — the broadcast
     * equivalent of activeOfficersFor(), used by OpenRenewalSeason to notify
     * every organization whose renewal just came due in a single query
     * rather than one activeOfficersFor() call per organization.
     *
     * @param  iterable<int, int>  $organizationIds
     * @return Collection<int, User>
     */
    public function activeOfficersForOrganizations(iterable $organizationIds): Collection
    {
        return User::query()
            ->whereHas('organizationMemberships', fn ($q) => $q
                ->whereIn('organization_id', $organizationIds)
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

    /**
     * The org's currently active president, or null if the seat is vacant.
     * Used to snapshot "Prepared by: [PRESIDENT'S NAME]" once at Activity
     * Proposal submission (App\ActivityProposals\SubmitActivityProposal) —
     * never for a live read at print time, which is the bug this method
     * replaces (see App\Printing\ActivityProposalForm's docblock history).
     */
    public function activePresidentFor(Organization $organization): ?User
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', OfficerPosition::President->value)
            ->where('is_active', true)
            ->with('user')
            ->first()
            ?->user;
    }

    /**
     * Closes every currently-active holder of a seat, stamping `ended_at` —
     * the single chokepoint for turnover so `is_active` and `ended_at`
     * always change together. Deliberately a no-op when there is no active
     * holder (a vacant seat), so calling this defensively never overwrites
     * an already-recorded end date on an inactive row.
     */
    public function closeActiveHolders(Organization $organization, OfficerPosition $position, ?CarbonInterface $at = null): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => $at ?? now()]);
    }

    /**
     * Closes a single membership row, stamping `ended_at`. A no-op if the
     * row is already inactive — re-deactivating an already-closed row must
     * never overwrite its real recorded end date with today's.
     */
    public function close(OrganizationMembership $membership, ?CarbonInterface $at = null): void
    {
        if (! $membership->is_active) {
            return;
        }

        $membership->update(['is_active' => false, 'ended_at' => $at ?? now()]);
    }

    /**
     * Closes every OTHER active seat this student holds in this same org
     * (never the seat named by $keeping) — so being bound/approved into a
     * new seat never leaves someone holding two seats in the same org at
     * once. Shared chokepoint for BindOrganizationOfficer (the adviser's
     * direct path) and App\Organizations\Admin\ApproveOfficerChange (the
     * SDAO-admin finalize path), so the two ways of turning over an officer
     * can't drift apart on this rule.
     */
    public function closeOtherActiveSeats(Organization $organization, User $student, OfficerPosition $keeping, ?CarbonInterface $at = null): int
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $student->id)
            ->where('position', '!=', $keeping->value)
            ->where('is_active', true)
            ->update(['is_active' => false, 'ended_at' => $at ?? now()]);
    }
}
