<?php

namespace App\Organizations\Admin;

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\Role;
use App\Models\OfficerChangeRequest;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\OfficerChangeDeclinedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Declines a pending officer change request. Terminal — same "no revival"
 * spirit as DeclineJoinRequest: the officer must file a brand-new request
 * (RequestOfficerChange already allows this, since it only blocks on an
 * existing PENDING row for the same seat).
 */
class DeclineOfficerChange
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, OfficerChangeRequest $changeRequest, ?string $comment = null): OfficerChangeRequest
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may finalize an officer change request.');
        }

        // Re-read under a row lock so a decline racing an approve (or a
        // second decline) can't pass the "still pending" check on a stale copy
        // and overwrite a decision that was just made.
        DB::transaction(function () use ($changeRequest, $actor, $comment) {
            OfficerChangeRequest::query()->whereKey($changeRequest->id)->lockForUpdate()->first();
            $changeRequest->refresh();

            if ($changeRequest->status !== OfficerChangeRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'officer_change_request' => 'This request has already been decided.',
                ]);
            }

            $changeRequest->update([
                'status' => OfficerChangeRequestStatus::Declined,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_comment' => $comment,
            ]);
        });

        try {
            $changeRequest->requester->notify(new OfficerChangeDeclinedNotification($changeRequest));
        } catch (\Throwable $e) {
            Log::error('Officer-change-declined notification failed to dispatch', [
                'officer_change_request_id' => $changeRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $changeRequest;
    }
}
