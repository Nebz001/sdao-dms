<?php

namespace App\Identity\Admin;

use App\Enums\AccountStatus;
use App\Enums\AdviserTermOutcome;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\ApproverProvisionedNotification;
use App\Organizations\Admin\AdviserChange;
use App\Organizations\Admin\AssignOrganizationAdviser;
use App\Organizations\AdviserChangeNotifier;
use App\Support\PersonName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SDAO-admin account creation for approvers (adviser, program chair, dean,
 * principal, SDAO member, the three directors). Approvers are never
 * self-registered (CLAUDE.md "Identity & accounts") — this is the only
 * production code path (besides seeders) that creates an approver account.
 *
 * The new account gets a random one time password, unique per account, and
 * is flagged must_change_password so the approver is sent to a dedicated
 * change page at first login (see EnsurePasswordIsChanged).
 * ApproverProvisionedNotification emails that password to the approver right
 * away. No password is shared between accounts and none is hardcoded.
 *
 * SDAO membership is multi-holder, so provisioning one only ever ADDS a seat.
 * To swap a person out, pass `replacesUserId`: that member's SDAO role row is
 * removed in the same transaction. By default the account is also deactivated
 * (AccountDeactivator) in that transaction, so it can no longer log in;
 * `deactivateReplaced: false` keeps it active. Its history is never touched.
 *
 * An adviser provisioned WITH an organization takes that organization's seat
 * through AssignOrganizationAdviser — the same locked, history-keeping swap SDAO
 * uses to assign an existing pool adviser — in the same transaction as the new
 * account, so a collision rolls the account back too. SDAO chooses whether the
 * outgoing adviser returns to the pool or is deactivated (`outgoingAdviser`).
 */
class ProvisionApprover
{
    public function __construct(
        private readonly AccountDeactivator $deactivator,
        private readonly AssignOrganizationAdviser $assignAdviser,
        private readonly AdviserChangeNotifier $adviserNotifier,
    ) {}

    /**
     * True when the last execute() created the account but could not queue the
     * welcome email. The one time password is not stored anywhere, so the
     * caller must tell the admin the person has to use Forgot password.
     */
    public bool $welcomeEmailFailed = false;

    /** Letters and digits only, so it survives being typed in from an email. */
    private const int TEMPORARY_PASSWORD_LENGTH = 16;

    /**
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     * @param  AdviserTermOutcome  $outgoingAdviser  Adviser role only: what happens to the organization's current adviser
     * @param  bool  $verifyOutgoingAdviser  Adviser role only: refuse unless the current adviser is still $expectedOutgoingAdviserId
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, string $firstName, string $lastName, string $email, Role $role, array $scope, ?string $idNumber = null, ?int $replacesUserId = null, bool $deactivateReplaced = true, AdviserTermOutcome $outgoingAdviser = AdviserTermOutcome::ReturnedToPool, bool $verifyOutgoingAdviser = false, ?int $expectedOutgoingAdviserId = null): User
    {
        if (! $actor->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember)) {
            throw new AuthorizationException('Only an SDAO member may provision approver accounts.');
        }

        if ($role === Role::Student) {
            throw ValidationException::withMessages([
                'role' => 'Students self-register and are bound by their adviser; they are never admin-provisioned.',
            ]);
        }

        $this->guardScopeMatchesRole($role, $scope);
        $this->guardReplacement($actor, $role, $replacesUserId);

        $this->welcomeEmailFailed = false;

        $temporaryPassword = Str::password(self::TEMPORARY_PASSWORD_LENGTH, symbols: false);

        $firstName = PersonName::stripTitle($firstName);
        $lastName = PersonName::clean($lastName);
        $name = PersonName::join($firstName, $lastName);

        $adviserChange = null;

        $user = DB::transaction(function () use ($actor, $name, $firstName, $lastName, $email, $idNumber, $role, $scope, $replacesUserId, $deactivateReplaced, $temporaryPassword, $outgoingAdviser, $verifyOutgoingAdviser, $expectedOutgoingAdviserId, &$adviserChange) {
            if ($replacesUserId !== null) {
                $this->retireSdaoMember($replacesUserId);

                // Same transaction as the role removal and the new account, so a
                // failure further down rolls all three back together.
                if ($deactivateReplaced) {
                    $this->deactivator->deactivate(
                        User::query()->findOrFail($replacesUserId),
                        $actor,
                        "Replaced as SDAO member by {$name}",
                    );
                }
            }

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'id_number' => $idNumber,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                // The admin vouches for the address — approvers are trusted
                // accounts and must not hit the email/account verification walls
                // before they can log in.
                'email_verified_at' => now(),
                'account_status' => AccountStatus::Verified,
            ]);

            if ($role->hasSingleGlobalHolder()) {
                // Must never have more than one row — RoleDirectory::
                // resolveGlobal() can only resolve a single holder. Provisioning
                // a new one supersedes whichever account currently holds it,
                // rather than creating an ambiguous second row.
                RoleAssignment::updateOrCreate(
                    ['role' => $role, 'school_id' => null, 'program_id' => null, 'organization_id' => null],
                    ['user_id' => $user->id],
                );
            } elseif ($role === Role::Adviser) {
                // Always created in the pool first; assigning to an organization
                // is the shared swap's job, so a new account and an existing pool
                // adviser take exactly the same path.
                RoleAssignment::create(['user_id' => $user->id, 'role' => $role]);

                if (($scope['organization_id'] ?? null) !== null) {
                    $adviserChange = $this->assignAdviser->swap(
                        $actor,
                        $this->organizationFor((int) $scope['organization_id']),
                        $user,
                        $outgoingAdviser,
                        $verifyOutgoingAdviser,
                        $expectedOutgoingAdviserId,
                    );
                }
            } else {
                $this->retireIncumbent($role, $scope);

                RoleAssignment::create([
                    'user_id' => $user->id,
                    'role' => $role,
                    'school_id' => $scope['school_id'] ?? null,
                    'program_id' => $scope['program_id'] ?? null,
                    'organization_id' => $scope['organization_id'] ?? null,
                ]);
            }

            return $user;
        });

        if ($adviserChange instanceof AdviserChange) {
            $this->adviserNotifier->announce($adviserChange);
        }

        try {
            $user->notify(new ApproverProvisionedNotification($role, $temporaryPassword));
        } catch (\Throwable $e) {
            $this->welcomeEmailFailed = true;

            Log::error('Approver-provisioned notification failed to dispatch', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    /**
     * Replacing is only defined for SDAO members (the one multi-holder role,
     * so nothing else retires its holders), and an SDAO member cannot remove
     * their own role mid-request and lock themselves out of the admin area.
     *
     * @throws ValidationException
     */
    private function guardReplacement(User $actor, Role $role, ?int $replacesUserId): void
    {
        if ($replacesUserId === null) {
            return;
        }

        if ($role !== Role::SdaoMember) {
            throw ValidationException::withMessages([
                'replaces_user_id' => 'Only an SDAO member can be replaced when provisioning an SDAO member.',
            ]);
        }

        if ($replacesUserId === $actor->id) {
            throw ValidationException::withMessages([
                'replaces_user_id' => 'You cannot replace yourself. Ask another SDAO member to do it.',
            ]);
        }
    }

    /**
     * Removes ONLY the outgoing SDAO member's role row. Their user account,
     * document_transitions, document_step_approvals and notifications are
     * left exactly as they are: the append-only history keeps naming them,
     * and they simply stop resolving as an SDAO approver
     * (RoleDirectory::sdaoMembers() reads role_assignments live).
     *
     * @throws ValidationException
     */
    private function retireSdaoMember(int $userId): void
    {
        $retired = RoleAssignment::query()
            ->where('role', Role::SdaoMember)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->delete();

        if ($retired === 0) {
            throw ValidationException::withMessages([
                'replaces_user_id' => 'That user is not a current SDAO member.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function organizationFor(int $organizationId): Organization
    {
        return Organization::query()->find($organizationId)
            ?? throw ValidationException::withMessages(['organization_id' => 'That organization no longer exists.']);
    }

    /**
     * A scope-bound single-holder role (chair/dean/principal) has exactly one
     * seat per (role, scope) pair. Provisioning a replacement must vacate that
     * seat first — otherwise RoleDirectory is left choosing between two live
     * rows, and the replacement (the higher id) never wins (see
     * RoleDirectory::resolveScoped()'s first-assigned-wins rule), so the newly
     * provisioned approver is silently neither notified nor authorized for
     * anything. A no-op for SdaoMember, which is not single-holder per scope.
     *
     * Adviser is NOT handled here: its seat is swapped by
     * AssignOrganizationAdviser, which unbinds the previous adviser back to the
     * pool (never deleting the role row, so an in-flight registration naming
     * them still validates) and keeps the dated history. Chair/Dean/Principal
     * have no pool concept — their scope key is always required
     * (guardScopeMatchesRole()), so a scope-less leftover row would be
     * meaningless — their previous holder's row is deleted outright.
     *
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     */
    private function retireIncumbent(Role $role, array $scope): void
    {
        if (! $role->hasSingleScopedHolder()) {
            return;
        }

        $column = $role->scopeColumn();
        $value = $scope[$column] ?? null;

        if ($value === null) {
            return;
        }

        RoleAssignment::query()
            ->where('role', $role)
            ->where($column, $value)
            ->lockForUpdate()
            ->get()
            ->each->delete();
    }

    /**
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     *
     * @throws ValidationException
     */
    private function guardScopeMatchesRole(Role $role, array $scope): void
    {
        $expectedKey = $role->scopeColumn();

        $providedKeys = array_keys(array_filter($scope, fn ($value) => $value !== null));

        // Role::Adviser is the ONE deliberate exception to strict scope-matching,
        // tied to the Phase 2 item-5 founding-flow redesign: a student proposing
        // a brand-new organization picks an adviser from a pool of admin-
        // provisioned accounts that are NOT yet assigned to any org — the
        // adviser is only actually bound to an organization_id at the moment
        // SDAO approves that founding registration (see
        // App\Registrations\ApproveOrganizationRegistration). So provisioning
        // an Adviser with NO scope (available, pending assignment) must be
        // allowed, alongside the normal "assign immediately" path for admin
        // convenience. This asymmetry with Dean/ProgramChair/Principal below —
        // which still require their scope exactly, unconditionally — is
        // intentional and should NOT be "fixed" back to strict parity.
        if ($role === Role::Adviser) {
            if ($providedKeys !== [] && $providedKeys !== ['organization_id']) {
                throw ValidationException::withMessages([
                    'scope' => "{$role->label()} takes either no scope (available, unassigned) or exactly an organization_id.",
                ]);
            }

            return;
        }

        if ($expectedKey === null) {
            if ($providedKeys !== []) {
                throw ValidationException::withMessages([
                    'scope' => "{$role->label()} is a global role and takes no school/program/organization scope.",
                ]);
            }

            return;
        }

        if ($providedKeys !== [$expectedKey]) {
            throw ValidationException::withMessages([
                'scope' => "{$role->label()} requires exactly a {$expectedKey}.",
            ]);
        }
    }
}
