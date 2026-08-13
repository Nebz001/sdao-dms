<?php

namespace App\Organizations;

use App\Enums\JoinRequestStatus;
use App\Models\OrganizationJoinRequest;
use App\Models\User;
use App\Notifications\JoinRequestDeclinedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Declines a pending join request. Terminal — same "no revival" spirit as a
 * rejected Document: the student must file a brand-new request (RequestToJoinOrganization
 * already allows this, since it only blocks on an existing PENDING row).
 */
class DeclineJoinRequest
{
    /**
     * @throws ValidationException
     */
    public function execute(User $actor, OrganizationJoinRequest $joinRequest, ?string $comment = null): OrganizationJoinRequest
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw ValidationException::withMessages([
                'join_request' => 'This request has already been decided.',
            ]);
        }

        $joinRequest->update([
            'status' => JoinRequestStatus::Declined,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_comment' => $comment,
        ]);

        try {
            $joinRequest->user->notify(new JoinRequestDeclinedNotification($joinRequest));
        } catch (\Throwable $e) {
            Log::error('Join-request-declined notification failed to dispatch', [
                'join_request_id' => $joinRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $joinRequest;
    }
}
