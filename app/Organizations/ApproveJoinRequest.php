<?php

namespace App\Organizations;

use App\Enums\JoinRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\JoinRequestApprovedNotification;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Grants a pending join request, binding the student as the chosen officer
 * position. Deliberately does NOT delegate to BindOrganizationOfficer — that
 * class hard-requires the actor to be the org's adviser
 * (RoleDirectory::isAdviserOf), but this feature explicitly allows an active
 * officer to decide join requests too (Gate::authorize('manageJoinRequests', ...)
 * at the controller, checked before this class ever runs). The bind body
 * below mirrors BindOrganizationOfficer's exactly, minus that one check.
 *
 * Unlike a Manage Officers turnover, approving a join request never silently
 * displaces an existing officer — if the requested position is already
 * actively held, approval is blocked with a message pointing at Manage
 * Officers, since turnover should stay a deliberate adviser action, not an
 * incidental side effect of approving an unrelated request.
 */
class ApproveJoinRequest
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(User $actor, OrganizationJoinRequest $joinRequest, OfficerPosition $position): OrganizationMembership
    {
        $student = $joinRequest->user;
        $organization = $joinRequest->organization;

        $membership = $this->membershipService->runSeatChange($organization, $student, 'join_request', function () use ($actor, $joinRequest, $student, $organization, $position) {
            // Re-read everything under the org + student lock: a concurrent
            // approve of the same request, or a bind into the same seat, may
            // have landed since the page was rendered.
            OrganizationJoinRequest::query()->whereKey($joinRequest->id)->lockForUpdate()->first();
            $joinRequest->refresh();
            $student->refresh();

            if ($joinRequest->status === JoinRequestStatus::Withdrawn) {
                throw ValidationException::withMessages([
                    'join_request' => "This request was closed automatically because {$student->name} has since become an officer. Nothing to approve.",
                ]);
            }

            if ($joinRequest->status !== JoinRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'join_request' => 'This request has already been decided.',
                ]);
            }

            if (! $student->isVerifiedAccount()) {
                throw ValidationException::withMessages([
                    'join_request' => 'This student\'s account has not been SDAO-verified yet.',
                ]);
            }

            // A student who already holds a seat — in THIS organization or any
            // other — can't be given another. (Joining must never be a route to
            // holding both seats of one org: the check below used to exclude this
            // organization, which is exactly how that slipped through.) Normally
            // such a request has already been withdrawn automatically; this
            // catches any that predate that or slip past it.
            $ownSeat = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('user_id', $student->id)
                ->active()
                ->first();

            if ($ownSeat !== null) {
                throw ValidationException::withMessages([
                    'join_request' => "{$student->name} is already {$ownSeat->position->label()} of this organization, so this request can't be approved. Decline it instead.",
                ]);
            }

            if ($this->membershipService->hasActiveMembershipElsewhere($student)) {
                throw ValidationException::withMessages([
                    'join_request' => 'This student is already an active officer of a different organization.',
                ]);
            }

            $positionFilled = OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('position', $position->value)
                ->where('is_active', true)
                ->exists();

            if ($positionFilled) {
                throw ValidationException::withMessages([
                    'join_request' => "{$position->label()} is already filled for this organization. Deactivate the current holder via Manage Officers, have the org file an officer change request, or approve this join request as a different position.",
                ]);
            }

            $now = now();

            $membership = OrganizationMembership::create([
                'user_id' => $student->id,
                'organization_id' => $organization->id,
                'position' => $position->value,
                'academic_year' => AcademicYear::current(),
                'is_active' => true,
                'started_at' => $now,
            ]);

            $joinRequest->update([
                'status' => JoinRequestStatus::Approved,
                'decided_by' => $actor->id,
                'decided_at' => $now,
            ]);

            return $membership;
        });

        try {
            $student->notify(new JoinRequestApprovedNotification($joinRequest));
        } catch (\Throwable $e) {
            Log::error('Join-request-approved notification failed to dispatch', [
                'join_request_id' => $joinRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $membership;
    }
}
