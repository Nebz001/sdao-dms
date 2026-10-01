<?php

namespace App\Identity\Admin;

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Lifts a deactivation. Sessions and tokens that were ended stay ended; the
 * person simply signs in again.
 */
class ReactivateAccount
{
    public function __construct(private readonly AccountDeactivator $deactivator) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $account): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may reactivate accounts.');
        }

        if (! $account->isDeactivated()) {
            throw ValidationException::withMessages(['account' => 'This account is not deactivated.']);
        }

        $this->deactivator->reactivate($account);

        return $account->refresh();
    }
}
