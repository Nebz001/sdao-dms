<?php

namespace App\Organizations;

use App\Enums\JoinRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\JoinRequestApprovedNotification;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;
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
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'join_request' => 'This request has already been decided.',
            ]);
        }

        $student = $joinRequest->user;
        $organization = $joinRequest->organization;

        if (! $student->isVerifiedAccount()) {
            throw ValidationException::withMessages([
                'join_request' => 'This student\'s account has not been SDAO-verified yet.',
            ]);
        }

        if ($this->membershipService->hasActiveMembershipElsewhere($student, $organization)) {
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
                'join_request' => "{$position->label()} is already filled for this organization. Deactivate the current holder via Manage Officers first, or approve as a different position.",
            ]);
        }

        $membership = DB::transaction(function () use ($actor, $joinRequest, $student, $organization, $position) {
            $membership = OrganizationMembership::create([
                'user_id' => $student->id,
                'organization_id' => $organization->id,
                'position' => $position->value,
                'academic_year' => AcademicYear::current(),
                'is_active' => true,
            ]);

            $joinRequest->update([
                'status' => JoinRequestStatus::Approved,
                'decided_by' => $actor->id,
                'decided_at' => now(),
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
