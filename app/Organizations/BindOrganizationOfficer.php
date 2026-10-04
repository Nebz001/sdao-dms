<?php

namespace App\Organizations;

use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Adviser-initiated officer binding — TURNOVER ONLY (Phase 2 item 5): used to
 * replace a president/secretary on an already-Approved, already-real
 * organization. Founding a brand-new org's first president is a SEPARATE
 * path now (App\Registrations\SubmitOrganizationRegistration submits the
 * proposal; App\Registrations\ApproveOrganizationRegistration binds the
 * founding president once SDAO approves) — this class's own
 * isAdviserOf($actor, $organization) check couldn't authorize a founding
 * bind anyway, since the actor there is SDAO, not an adviser the org doesn't
 * have bound yet.
 *
 * Invariant: at most one active president and one active secretary per org,
 * and no single student holds both seats at once. On turnover the old
 * holder is deactivated (never deleted) and a new active membership is
 * created; if the incoming student already actively holds the org's OTHER
 * seat, that seat is closed too (see execute()).
 */
class BindOrganizationOfficer
{
    public function __construct(
        private readonly RoleDirectory $roleDirectory,
        private readonly OrganizationMembershipService $membershipService,
        private readonly EligibleOfficerCandidates $candidates,
        private readonly OfficerSeatNotifier $seatNotifier,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        User $student,
        OfficerPosition $position,
        ?string $academicYear = null,
    ): OrganizationMembership {
        if (! $this->roleDirectory->isAdviserOf($actor, $organization)) {
            throw new AuthorizationException('Only the org\'s adviser may bind officers.');
        }

        $outgoingHolderIds = [];
        $withdrawnJoinRequests = collect();

        $membership = $this->membershipService->runSeatChange($organization, $student, 'user_id', function () use ($organization, $student, $position, $academicYear, &$outgoingHolderIds, &$withdrawnJoinRequests) {
            // Every guard below runs UNDER the org + student lock, against
            // fresh state: a concurrent bind may have changed either since
            // the picker was rendered or the request was validated.
            $student->refresh();

            if (! $student->isVerifiedAccount()) {
                throw ValidationException::withMessages([
                    'user_id' => 'This student\'s account has not been SDAO-verified yet.',
                ]);
            }

            // One organization per student (Phase 2 item 4): a student already
            // actively bound elsewhere cannot be bound here too, whether this
            // is a founding bind or officer turnover.
            if ($this->membershipService->hasActiveMembershipElsewhere($student, $organization)) {
                throw ValidationException::withMessages([
                    'user_id' => 'This student is already an active officer of a different organization.',
                ]);
            }

            // The same eligibility the picker uses (approver-role accounts, an
            // in-flight registration elsewhere) — re-checked here so a forged
            // request can't bind someone the picker would never have offered.
            if (! $this->candidates->matches($organization, $student)) {
                throw ValidationException::withMessages([
                    'user_id' => 'This student is not eligible to be bound as an officer right now.',
                ]);
            }

            // Hoisted once — sharing one instant between the outgoing term's
            // ended_at and the incoming term's started_at keeps the two
            // exactly equal, with no gap and no overlap.
            $now = now();

            // Who is about to lose the seat — read BEFORE closing, to tell them after commit.
            $outgoingHolderIds = $this->membershipService->activeHolderIds($organization, $position);

            // Turnover: deactivate any existing active holder of this position.
            $this->membershipService->closeActiveHolders($organization, $position, $now);

            // If this student already actively holds the org's OTHER seat,
            // close it too — binding them into a new seat must leave them
            // holding exactly one, not both (same rule ApproveOfficerChange
            // enforces on the admin-finalize path; see
            // OrganizationMembershipService::closeOtherActiveSeats).
            $this->membershipService->closeOtherActiveSeats($organization, $student, $position, $now);

            $membership = OrganizationMembership::create([
                'user_id' => $student->id,
                'organization_id' => $organization->id,
                'position' => $position->value,
                'academic_year' => $academicYear ?? AcademicYear::current(),
                'is_active' => true,
                'started_at' => $now,
            ]);

            // Settled: a requester who just lost their seat has no authority left.
            $this->membershipService->withdrawOrphanedChangeRequests($organization->id);

            // ...and a student who just gained a seat can't still be asking for one.
            $withdrawnJoinRequests = $this->membershipService->withdrawPendingJoinRequestsFor($student, $organization);

            return $membership;
        });

        // After commit, best-effort: the replaced officer(s) learn their seat
        // ended, and the student learns they were bound. A student re-bound
        // into the very seat they already held is not "replaced".
        $this->seatNotifier->ended(
            array_diff($outgoingHolderIds, [$student->id]),
            $organization,
            $position,
            OfficerSeatEndReason::Replaced,
        );
        $this->seatNotifier->granted($student, $organization, $position);
        $this->seatNotifier->joinRequestsWithdrawn($student, $withdrawnJoinRequests, $organization);

        return $membership;
    }
}
