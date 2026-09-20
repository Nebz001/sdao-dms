<?php

namespace App\Organizations\Admin;

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\Role;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\OfficerChangeApprovedNotification;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Finalizes a pending officer-change request, performing the actual
 * turnover. SDAO-admin only — re-asserted here as defence in depth behind
 * the `can:access-admin` route middleware, same idiom as
 * App\Identity\Admin\VerifyAccount.
 *
 * Every data guard is re-run from scratch against CURRENT state, not the
 * request's submit-time snapshot — the request may be days old, and the
 * nominee's eligibility, or the seat itself, may have changed since. Notably
 * NOT a guard: "the seat is still held by whoever outgoing_user_id names."
 * Blocking on that would dead-end the queue every time the adviser did an
 * intervening turnover via the unchanged BindOrganizationOfficer path — this
 * action always closes whoever CURRENTLY holds the seat, and the admin queue
 * surfaces outgoing_user_id next to the live holder so a stale request can
 * be declined instead of blindly approved.
 */
class ApproveOfficerChange
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly EligibleOfficerCandidates $candidates,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, OfficerChangeRequest $changeRequest): OrganizationMembership
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may finalize an officer change request.');
        }

        if ($changeRequest->status !== OfficerChangeRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'officer_change_request' => 'This request has already been decided.',
            ]);
        }

        $organization = $changeRequest->organization;
        $position = $changeRequest->position;
        $nominee = $changeRequest->nominee;

        if (! $nominee->isVerifiedAccount()) {
            throw ValidationException::withMessages([
                'officer_change_request' => 'This student\'s account is no longer SDAO-verified.',
            ]);
        }

        if ($this->membershipService->hasActiveMembershipElsewhere($nominee, $organization)) {
            throw ValidationException::withMessages([
                'officer_change_request' => 'This student is now an active officer of a different organization.',
            ]);
        }

        if (! $this->candidates->matches($organization, $nominee)) {
            throw ValidationException::withMessages([
                'officer_change_request' => 'This student is no longer eligible to be bound as an officer.',
            ]);
        }

        $alreadyHoldsPosition = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->where('user_id', $nominee->id)
            ->where('is_active', true)
            ->exists();

        if ($alreadyHoldsPosition) {
            throw ValidationException::withMessages([
                'officer_change_request' => 'They already hold this position — decline this request instead.',
            ]);
        }

        // Hoisted once — sharing one instant between the outgoing term's
        // ended_at, any auto-closed dual-seat term's ended_at, and the
        // incoming term's started_at keeps them exactly aligned.
        $now = now();

        $membership = DB::transaction(function () use ($organization, $position, $nominee, $changeRequest, $actor, $now) {
            // 1. Close the freshly-resolved live holder(s) of the seat —
            //    never trusting outgoing_user_id, which is a submit-time
            //    snapshot only.
            $this->membershipService->closeActiveHolders($organization, $position, $now);

            // 2. If the nominee already holds the OTHER seat in this same
            //    org, close it too — approving "Secretary -> President"
            //    leaves them holding exactly one seat, not both. Shared with
            //    the adviser's direct-bind path (BindOrganizationOfficer),
            //    which enforces the same rule via the same method.
            $this->membershipService->closeOtherActiveSeats($organization, $nominee, $position, $now);

            // 3. Open the incoming term at the same instant.
            $membership = OrganizationMembership::create([
                'user_id' => $nominee->id,
                'organization_id' => $organization->id,
                'position' => $position->value,
                'academic_year' => AcademicYear::current(),
                'is_active' => true,
                'started_at' => $now,
            ]);

            // 4. Decide the request last, stamped with the same instant.
            $changeRequest->update([
                'status' => OfficerChangeRequestStatus::Approved,
                'decided_by' => $actor->id,
                'decided_at' => $now,
            ]);

            return $membership;
        });

        try {
            $changeRequest->requester->notify(new OfficerChangeApprovedNotification($changeRequest));
            $nominee->notify(new OfficerChangeApprovedNotification($changeRequest));
        } catch (\Throwable $e) {
            Log::error('Officer-change-approved notification failed to dispatch', [
                'officer_change_request_id' => $changeRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $membership;
    }
}
