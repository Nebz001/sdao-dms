<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\AdviserTerm;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Organizations\OfficerSeatNotifier;
use App\Organizations\OrganizationMembershipService;
use App\Support\CurrentPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Wraps ApprovalEngine::approve() with the founding-flow side effects
 * (Phase 2 item 5): a race-condition re-check on the chosen adviser (same
 * defensive pattern as VenueConflictChecker's approve-time re-check from
 * Slice 3 — two students could have picked the same then-available adviser
 * while both proposals were pending), and — only once the SDAO quorum is
 * actually satisfied — binding the adviser and the founding student as
 * President for the first time, and stamping the registration detail's
 * academic period/coverage (see the inline comment at the stamping site for
 * why this happens at approve time, not submit time — the asymmetry with
 * SubmitOrganizationRenewal is deliberate).
 */
class ApproveOrganizationRegistration
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly OfficerSeatNotifier $seatNotifier,
    ) {}

    /**
     * @throws ValidationException if the chosen adviser is no longer
     *                             available, or the submitting account no
     *                             longer exists
     */
    public function execute(Document $document, User $actor, ?string $comment = null): Document
    {
        // Defensive: WithdrawInFlightRegistrations rejects a document before
        // its submitter's account can be deleted, so submitted_by should
        // never actually be null here — but this must be checked BEFORE
        // engine->approve() below, not after: that call can itself finalize
        // the quorum and flip status to Approved, and there'd be no undoing
        // that if the OrganizationMembership::create() further down then hit
        // its NOT NULL constraint on a null user_id.
        if ($document->submitted_by === null) {
            throw ValidationException::withMessages([
                'approve' => 'Cannot approve: the submitting account no longer exists. Reject this registration instead.',
            ]);
        }

        $document->loadMissing('registrationDetail');
        $adviserId = $document->registrationDetail->adviser_id;

        // Race guard, same pattern as the adviser exclusivity re-check below:
        // the chosen adviser may have been deactivated since this was submitted.
        if (User::query()->whereKey($adviserId)->whereNotNull('deactivated_at')->exists()) {
            throw ValidationException::withMessages([
                'approve' => 'Cannot approve: the chosen adviser account has been deactivated. Return the document so the student can pick a different adviser.',
            ]);
        }

        $adviserAssignment = RoleAssignment::query()
            ->where('user_id', $adviserId)
            ->where('role', Role::Adviser->value)
            ->first();

        // Race-condition re-check: another registration may have bound this
        // same adviser to a different org since this one was submitted.
        if ($adviserAssignment !== null
            && $adviserAssignment->organization_id !== null
            && $adviserAssignment->organization_id !== $document->organization_id) {
            throw ValidationException::withMessages([
                'approve' => 'Cannot approve: the chosen adviser is now assigned to a different organization. Return the document so the student can pick a different adviser.',
            ]);
        }

        $founder = User::query()->findOrFail($document->submitted_by);

        // One serialized unit, locked on the ORG and on the FOUNDING STUDENT.
        // Two registrations by the same student (only possible via a double-
        // submit race — see SubmitOrganizationRegistration) approved at the
        // same moment lock different orgs but the SAME student, so the second
        // waits, then sees the first's President seat and is refused below.
        // Approval and binding also now commit or roll back together.
        $withdrawnJoinRequests = collect();

        $this->membershipService->runSeatChange($document->organization, $founder, 'approve', function () use ($document, $actor, $comment, $founder, $adviserAssignment, &$withdrawnJoinRequests) {
            // Fresh state, under the lock: the founder may have become an
            // active officer elsewhere since this was submitted.
            if ($this->membershipService->hasActiveMembershipElsewhere($founder, $document->organization)) {
                throw ValidationException::withMessages([
                    'approve' => 'Cannot approve: the founding student is now an active officer of a different organization. Reject this registration instead.',
                ]);
            }

            $this->engine->approve($document, $actor, $comment);
            $document->refresh();

            // Only bind once the SDAO quorum is actually satisfied (both
            // members) — the first of two approvals does not yet flip status to
            // Approved, and binding must not happen prematurely.
            if ($document->status === DocumentStatus::Approved) {
                if ($adviserAssignment !== null) {
                    $adviserAssignment->update(['organization_id' => $document->organization_id]);

                    AdviserTerm::create([
                        'user_id' => $adviserAssignment->user_id,
                        'organization_id' => $document->organization_id,
                        'started_at' => now(),
                        'started_by' => $actor->id,
                    ]);
                }

                $period = CurrentPeriod::get();

                OrganizationMembership::create([
                    'user_id' => $document->submitted_by,
                    'organization_id' => $document->organization_id,
                    'position' => OfficerPosition::President->value,
                    'academic_year' => $period->academicYear,
                    'is_active' => true,
                    'started_at' => now(),
                ]);

                // A founder who had also asked to join some organization can't
                // still be asking now that they hold a seat.
                $withdrawnJoinRequests = $this->membershipService->withdrawPendingJoinRequestsFor($founder, $document->organization);

                // Stamped at APPROVE time (not submit time), unlike a renewal —
                // this records when the org actually became active, not when the
                // form happened to be filed. A registration approved during 3rd
                // term (renewal season) gets grace: it covers both the current
                // year AND next year, so the org isn't asked to renew days after
                // being founded — see SubmitOrganizationRenewal::eligibilityFor().
                $document->registrationDetail->update([
                    'academic_year' => $period->academicYear,
                    'term' => $period->term->value,
                    'covers_academic_year' => $period->isRenewalSeason() ? $period->nextAcademicYear() : $period->academicYear,
                ]);
            }
        });

        // After commit, best-effort: tell the founder any join request they had
        // open was closed now that they hold a seat.
        $this->seatNotifier->joinRequestsWithdrawn($founder, $withdrawnJoinRequests, $document->organization);

        return $document;
    }
}
