<?php

namespace App\Identity\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Backs the Undo link on the Verify / Reject toast: puts a just-reviewed
 * self-registered account back in the Pending Accounts queue.
 *
 * Only safe while nothing has been built on the decision. Once a verified
 * student has been bound to an organization or has filed a document, the
 * review can no longer be taken back. Notifications already sent to the
 * student stay sent.
 */
class RevertAccountReview
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $account): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may undo an account review.');
        }

        if ($account->account_status === AccountStatus::Unverified) {
            throw ValidationException::withMessages(['account' => 'This account is already pending review.']);
        }

        if (OrganizationMembership::query()->where('user_id', $account->id)->exists()
            || Document::query()->where('submitted_by', $account->id)->exists()) {
            throw ValidationException::withMessages([
                'account' => 'This account has already been bound to an organization or filed a document, so the review cannot be undone.',
            ]);
        }

        $account->update(['account_status' => AccountStatus::Unverified]);

        return $account;
    }
}
