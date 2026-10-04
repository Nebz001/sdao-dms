<?php

namespace App\Organizations;

use App\Enums\OfficerPosition;
use App\Enums\OfficerSeatEndReason;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OfficerSeatEndedNotification;
use App\Notifications\OfficerSeatGrantedNotification;
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
