<?php

namespace App\Organizations;

use App\Enums\FormType;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * of its org? True for any active, verified officer (President OR
     * Secretary — the only two OfficerPosition cases, so "active membership"
     * already IS "president or secretary") of the document's organization.
     *
     * The ONLY other grant is the founding exception: the original submitter
     * of an OrganizationRegistration while the org has no active officers at
     * all. A founding student proposing a brand-new organization has no
     * OrganizationMembership row yet on their own pending registration (that
     * binding only happens at SDAO approval — see
     * ApproveOrganizationRegistration), so membership alone would lock them
     * out of their own proposal. Every other form type is filed by an active
     * officer, and once an org has officers a removed officer's authorship
     * grants nothing — the roster, not authorship, decides who may act.
     */
    public function canActOnDocument(User $user, Document $document): bool
    {
        if ($this->activeMembershipFor($user, $document->organization) !== null) {
            return true;
        }

        return $document->form_type === FormType::OrganizationRegistration
            && $document->submitted_by === $user->id
            && ! $this->hasActiveOfficers($document->organization);
    }

    /**
     * Whether the org has any active president/secretary at all — the
     * "founding" test behind canActOnDocument()'s submitter exception.
     */
    public function hasActiveOfficers(Organization $organization): bool
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->active()
            ->exists();
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
     * The users currently holding a seat — read before closing it, so the
     * outgoing officers can be told once the change has committed.
     *
     * @return array<int, int>
     */
    public function activeHolderIds(Organization $organization, OfficerPosition $position): array
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->where('is_active', true)
            ->pluck('user_id')
            ->all();
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

        $this->withdrawOrphanedChangeRequests($membership->organization_id);
    }

    /**
     * A pending officer change request cannot outlive its requester's
     * authority: any pending request in this org whose filer no longer holds an
     * active seat in it is closed as Withdrawn, so SDAO is never asked to
     * approve something on behalf of someone who can no longer act, and nobody
     * is notified about a request they can no longer see.
     *
     * Call it AFTER a seat change has fully settled (seat closed and, for a
     * turnover, the incoming seat created) — never between the two, or an
     * officer who is merely being moved to the other seat would look orphaned.
     * Idempotent, and a no-op when nothing is orphaned.
     *
     * @return int how many requests were withdrawn
     */
    public function withdrawOrphanedChangeRequests(int $organizationId): int
    {
        return OfficerChangeRequest::query()
            ->where('organization_id', $organizationId)
            ->pending()
            ->whereNotIn('requested_by', OrganizationMembership::query()
                ->where('organization_id', $organizationId)
                ->active()
                ->select('user_id'))
            ->update([
                'status' => OfficerChangeRequestStatus::Withdrawn->value,
                'decided_at' => now(),
                'decision_comment' => 'Withdrawn automatically: the officer who filed this request no longer holds a seat in this organization.',
                'updated_at' => now(),
            ]);
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

    /**
     * Runs a seat change (bind / finalize / join-approve / file a request) as
     * ONE serialized unit per organization, so two actors changing the same
     * seat at the same time can never interleave.
     *
     * Inside a transaction it takes a row lock on the organization (every seat
     * change for that org queues behind it) and, when a student is involved,
     * on that student (so the same student can't be bound into two orgs at
     * once — there is no DB constraint for that rule). Lock order is always
     * org then student, and only one org is ever locked, so two changes can't
     * deadlock each other; a rare database deadlock is retried.
     *
     * The callback MUST re-read and re-check every guard itself: anything
     * validated before the lock was taken may have been changed by whoever held
     * it first. As a last line of defence, if a partial unique index (on the
     * seat, on a student's single active seat, or on a pending request) still
     * fires, the loser gets a plain "someone else just changed this" validation
     * error instead of a 500; the transaction has already rolled back, so
     * nothing is half-applied.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws ValidationException
     */
    public function runSeatChange(Organization $organization, ?User $student, string $errorKey, Closure $callback): mixed
    {
        try {
            return DB::transaction(function () use ($organization, $student, $callback) {
                Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

                if ($student !== null) {
                    User::query()->whereKey($student->id)->lockForUpdate()->first();
                }

                return $callback();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                $errorKey => 'Someone else just changed this seat. Refresh the page to see the current officers, then try again.',
            ]);
        }
    }
}
