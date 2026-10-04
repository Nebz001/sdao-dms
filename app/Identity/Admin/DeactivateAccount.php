<?php

namespace App\Identity\Admin;

use App\Enums\OfficerSeatEndReason;
use App\Enums\Role;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Organizations\OfficerSeatNotifier;
use App\Organizations\OrganizationMembershipService;
use App\Registrations\WithdrawInFlightRegistrations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SDAO retires an account.
 *
 * NON-STUDENT accounts: refuses to leave an approver seat empty. A document
 * routed to a seat with no active holder would have no approver, so the admin
 * must provision a replacement first (the SDAO replacement flow does exactly
 * that and deactivates in the same step through AccountDeactivator).
 *
 * STUDENT accounts: never refused. A student is deactivated because they left,
 * were removed, or their account may be compromised — exactly when waiting on
 * the adviser is wrong — and student seats aren't sole (two equal partners).
 * So the officer seat ends WITH the account, immediately, in one transaction
 * under the same org + student lock as every other seat change:
 *   - their active seat is closed (which also withdraws their pending officer
 *     change requests), kept as history, never deleted;
 *   - their pending join requests are withdrawn;
 *   - an in-flight registration they filed (only a founding student has no
 *     other officer to act on it) is withdrawn rather than stranded.
 * Other documents they submitted belong to the org and stay with its remaining
 * or next officer. After commit the student is mailed (the one way a
 * compromised-account owner finds out) and the org's adviser is told which
 * seat emptied and how many active officers remain. An org left with no
 * officers is allowed, not refused. Reactivating the account does NOT restore
 * the seat — the adviser can bind them again.
 */
class DeactivateAccount
{
    public function __construct(
        private readonly AccountDeactivator $deactivator,
        private readonly OrganizationMembershipService $membershipService,
        private readonly WithdrawInFlightRegistrations $withdrawRegistrations,
        private readonly OfficerSeatNotifier $seatNotifier,
    ) {}

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
            $this->deactivateStudent($actor, $account, $reason);

            return $account->refresh();
        }

        $this->guardSeats($account);

        $this->deactivator->deactivate($account, $actor, $reason);

        return $account->refresh();
    }

    /**
     * Ends the student's seat, closes their open requests and deactivates the
     * account as ONE unit (see the class docblock), then tells the people
     * affected once it has committed.
     *
     * @throws ValidationException
     */
    private function deactivateStudent(User $actor, User $account, ?string $reason): void
    {
        // At most one active seat in total (the per-student unique index).
        $seat = OrganizationMembership::query()->where('user_id', $account->id)->active()->with('organization')->first();

        $endedPosition = null;
        $remainingOfficers = 0;
        $closedChangeRequests = collect();

        $work = function () use ($actor, $account, $reason, &$endedPosition, &$remainingOfficers, &$closedChangeRequests) {
            $account->refresh();

            // Under the lock: read the seat again, it may have changed.
            foreach (OrganizationMembership::query()->where('user_id', $account->id)->active()->get() as $membership) {
                $this->membershipService->close($membership);   // also withdraws their pending change requests
                $endedPosition = $membership->position;
                $remainingOfficers = OrganizationMembership::query()
                    ->where('organization_id', $membership->organization_id)->active()->count();
            }

            $this->membershipService->withdrawAllPendingJoinRequestsBy($account, 'Withdrawn automatically: the student\'s account was deactivated.');
            // Officer change requests that NAME this student as the nominee can no
            // longer be approved — close them now rather than leave SDAO to spot
            // and hand-decline each one.
            $closedChangeRequests = $this->membershipService->withdrawPendingChangeRequestsNaming($account);
            // Only a FOUNDING registration (its org has no officer left to act on
            // it) is withdrawn; anything else stays with the org's officers.
            $this->withdrawRegistrations->execute($account, WithdrawInFlightRegistrations::DEACTIVATED_REASON, onlyWhereNoOfficers: true);
            $this->deactivator->deactivate($account, $actor, $reason);
        };

        if ($seat !== null) {
            $this->membershipService->runSeatChange($seat->organization, $account, 'account', $work);
        } else {
            DB::transaction(function () use ($account, $work) {
                User::query()->whereKey($account->id)->lockForUpdate()->first();
                $work();
            });
        }

        // After commit, best-effort. The officers who filed a request naming this
        // student are still sitting and waiting on an answer, so they are told it
        // was closed (never why the nominee is unavailable).
        $this->seatNotifier->changeRequestsClosed($closedChangeRequests);

        // Only a student who actually lost a seat is
        // announced: mail only (they can no longer reach the bell), and it never
        // names a replacement. The adviser learns which seat emptied and how
        // many active officers are left.
        if ($seat !== null && $endedPosition !== null) {
            $this->seatNotifier->ended([$account->id], $seat->organization, $endedPosition, OfficerSeatEndReason::AccountDeactivated);
            $this->seatNotifier->adviserOfDeactivatedOfficer($seat->organization, $account->name, $endedPosition, $remainingOfficers);
        }
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
