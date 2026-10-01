<?php

namespace App\Identity\Admin;

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * SDAO retires a non student account. Student accounts are out of scope for
 * now: deactivating a student officer also needs an organization side
 * (membership turnover), which is not built yet.
 *
 * Refuses to leave an approver seat empty. A document routed to a seat with
 * no active holder would have no approver, so the admin must provision a
 * replacement first (the SDAO replacement flow does exactly that and
 * deactivates in the same step through AccountDeactivator).
 */
class DeactivateAccount
{
    public function __construct(private readonly AccountDeactivator $deactivator) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, User $account, ?string $reason = null): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may deactivate accounts.');
        }

        if ($actor->is($account)) {
            throw ValidationException::withMessages(['account' => 'You cannot deactivate your own account.']);
        }

        if ($account->isDeactivated()) {
            throw ValidationException::withMessages(['account' => 'This account is already deactivated.']);
        }

        if ($account->isStudentAccount()) {
            throw ValidationException::withMessages(['account' => 'Student accounts cannot be deactivated here yet.']);
        }

        $this->guardSeats($account);

        $this->deactivator->deactivate($account, $actor, $reason);

        return $account->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function guardSeats(User $account): void
    {
        $account->loadMissing('roleAssignments.school', 'roleAssignments.program', 'roleAssignments.organization');

        foreach ($account->roleAssignments as $assignment) {
            if ($assignment->role === Role::SdaoMember) {
                $this->guardSdaoQuorum($account);

                continue;
            }

            if ($this->isSoleSeat($assignment)) {
                throw ValidationException::withMessages([
                    'account' => "This account is the {$this->seatLabel($assignment)}. Provision a replacement first, then deactivate it.",
                ]);
            }
        }
    }

    /**
     * Every single holder seat is sole by definition, except an Adviser with
     * no organization, which is just the unassigned pool.
     */
    private function isSoleSeat(RoleAssignment $assignment): bool
    {
        if ($assignment->role === Role::Adviser) {
            return $assignment->organization_id !== null;
        }

        return $assignment->role->hasSingleScopedHolder() || $assignment->role->hasSingleGlobalHolder();
    }

    /**
     * The dual approval SDAO step needs as many active members as its
     * required_approvals, so deactivating one must not drop below that.
     *
     * @throws ValidationException
     */
    private function guardSdaoQuorum(User $account): void
    {
        $required = (int) (WorkflowStep::query()->where('role', Role::SdaoMember->value)->max('required_approvals') ?: 1);

        $remaining = User::query()
            ->active()
            ->whereKeyNot($account->id)
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember->value))
            ->count();

        if ($remaining < $required) {
            throw ValidationException::withMessages([
                'account' => "SDAO approval needs {$required} active members. Provision a replacement SDAO member first, then deactivate this one.",
            ]);
        }
    }

    private function seatLabel(RoleAssignment $assignment): string
    {
        $scope = $assignment->organization?->name ?? $assignment->program?->name ?? $assignment->school?->name;

        return $scope !== null ? "{$assignment->role->label()} of {$scope}" : $assignment->role->label();
    }
}
