<?php

namespace App\Organizations;

use App\Enums\JoinRequestStatus;
use App\Models\OrganizationJoinRequest;
use App\Models\User;
use App\Notifications\JoinRequestDeclinedNotification;
use Illuminate\Support\Facades\DB;
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
        // Re-read under a row lock so a decline racing an approve (or the
        // automatic withdrawal) can't overwrite a decision that just landed.
        DB::transaction(function () use ($joinRequest, $actor, $comment) {
            OrganizationJoinRequest::query()->whereKey($joinRequest->id)->lockForUpdate()->first();
            $joinRequest->refresh();

            if ($joinRequest->status === JoinRequestStatus::Withdrawn) {
                throw ValidationException::withMessages([
                    'join_request' => 'This request was closed automatically because the student has since become an officer. Nothing to decline.',
                ]);
            }

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
        });

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
