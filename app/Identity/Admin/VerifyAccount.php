<?php

namespace App\Identity\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * SDAO marks a self-registered student's account Verified via the Pending
 * Accounts queue. Only after this can the student be adviser-bound as an
 * officer or submit any document (App\Organizations\OrganizationMembershipService).
 */
class VerifyAccount
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $account): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may verify accounts.');
        }

        if ($account->account_status !== AccountStatus::Unverified) {
            throw ValidationException::withMessages([
                'account' => 'Only a pending (Unverified) account can be verified.',
            ]);
        }

        $account->update(['account_status' => AccountStatus::Verified]);

        return $account;
    }
}
