<?php

namespace App\Identity\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SDAO declines a self-registered student's account via the Pending Accounts
 * queue. Rejected is a permanent, distinct terminal state — the row is never
 * deleted, but the account never gains submit/adviser-binding access either.
 */
class RejectAccount
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $account): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may reject accounts.');
        }

        if ($account->account_status !== AccountStatus::Unverified) {
            throw ValidationException::withMessages([
                'account' => 'Only a pending (Unverified) account can be rejected.',
            ]);
        }

        $account->update(['account_status' => AccountStatus::Rejected]);

        try {
            $account->notify(new AccountRejectedNotification);
        } catch (\Throwable $e) {
            Log::error('Account-rejected notification failed to dispatch', [
                'user_id' => $account->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $account;
    }
}
