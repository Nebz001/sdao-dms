<?php

namespace App\Organizations;

use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Identity\RoleDirectory;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\JoinRequestWithdrawnNotification;
use App\Notifications\OfficerAccountDeactivatedNotification;
use App\Notifications\OfficerChangeRequestClosedNotification;
use App\Notifications\OfficerSeatEndedNotification;
use App\Notifications\OfficerSeatGrantedNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort notices for the people on either side of a direct seat change
 * (adviser bind, Manage Officers deactivation, an approved change request's
 * outgoing holder). Always called AFTER the change has committed, and a
 * mail-provider failure is logged — never surfaced — so a notification problem
 * can never undo or block the change itself (same rule as every other
 * notification trigger in this app).
 */
class OfficerSeatNotifier
{
    public function granted(User $student, Organization $organization, OfficerPosition $position): void
    {
        try {
            $student->notify(new OfficerSeatGrantedNotification($organization, $position));
        } catch (\Throwable $e) {
            Log::error('Officer-seat-granted notification failed to dispatch', [
                'user_id' => $student->id,
                'organization_id' => $organization->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tells each requester that their officer change request was closed because
     * its nominee can no longer be considered. Only a requester who STILL holds
     * a seat in that org is told — they are waiting on an answer; anyone who has
     * lost their seat (including the deactivated student themselves) is not.
     *
     * @param  iterable<int, OfficerChangeRequest>  $withdrawn
     */
    public function changeRequestsClosed(iterable $withdrawn): void
    {
        foreach ($withdrawn as $changeRequest) {
            $stillOfficer = OrganizationMembership::query()
                ->where('organization_id', $changeRequest->organization_id)
                ->where('user_id', $changeRequest->requested_by)
                ->active()
                ->exists();

            if (! $stillOfficer) {
                continue;
            }

            try {
                $changeRequest->requester->notify(new OfficerChangeRequestClosedNotification($changeRequest));
            } catch (\Throwable $e) {
                Log::error('Officer-change-request-closed notification failed to dispatch', [
                    'officer_change_request_id' => $changeRequest->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Tells an organization's adviser that SDAO deactivated one of its officers'
     * accounts, which ended the seat without their involvement — and how many
     * active officers the org has left. Silent when the org has no resolvable
     * adviser (nobody to tell).
     */
    public function adviserOfDeactivatedOfficer(Organization $organization, string $officerName, OfficerPosition $position, int $remainingOfficers): void
    {
        try {
            $adviser = app(RoleDirectory::class)->adviserFor($organization);
        } catch (ModelNotFoundException) {
            return;
        }

        try {
            $adviser->notify(new OfficerAccountDeactivatedNotification($organization, $officerName, $position, $remainingOfficers));
        } catch (\Throwable $e) {
            Log::error('Officer-account-deactivated notification failed to dispatch', [
                'organization_id' => $organization->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tells a student their pending join request(s) were closed because they
     * just became an officer — only for requests to a DIFFERENT organization
     * than the one that bound them (see JoinRequestWithdrawnNotification).
     *
     * @param  iterable<int, OrganizationJoinRequest>  $withdrawn
     */
    public function joinRequestsWithdrawn(User $student, iterable $withdrawn, Organization $gainedIn): void
    {
        foreach ($withdrawn as $joinRequest) {
            if ($joinRequest->organization_id === $gainedIn->id) {
                continue;
            }

            try {
                $student->notify(new JoinRequestWithdrawnNotification($joinRequest));
            } catch (\Throwable $e) {
                Log::error('Join-request-withdrawn notification failed to dispatch', [
                    'join_request_id' => $joinRequest->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  iterable<int, int>  $userIds  the officers whose seat just ended
     */
    public function ended(iterable $userIds, Organization $organization, OfficerPosition $position, OfficerSeatEndReason $reason): void
    {
        foreach (User::query()->whereIn('id', collect($userIds)->unique()->all())->get() as $user) {
            try {
                $user->notify(new OfficerSeatEndedNotification($organization, $position, $reason));
            } catch (\Throwable $e) {
                Log::error('Officer-seat-ended notification failed to dispatch', [
                    'user_id' => $user->id,
                    'organization_id' => $organization->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
