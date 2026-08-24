<?php

namespace App\Identity\Admin;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Enums\ScopeType;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\ApproverProvisionedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SDAO-admin account creation for approvers (adviser, program chair, dean,
 * principal, SDAO member, the three directors). Approvers are never
 * self-registered (CLAUDE.md "Identity & accounts") — this is the only
 * production code path (besides seeders) that creates an approver account.
 *
 * The new account gets DEFAULT_PASSWORD — the same convention every
 * seeded/demo account already uses (see FixSeededAccountPasswords) — so it's
 * usable the instant it's created, instead of a random unusable password
 * stuck behind a reset link the approver never asked for.
 * ApproverProvisionedNotification emails that password to the approver right
 * away and points them at the existing Settings > Security page to change it
 * once they've logged in.
 */
class ProvisionApprover
{
    /** Matches the convention seeded/demo accounts already use (see FixSeededAccountPasswords). */
    public const string DEFAULT_PASSWORD = 'ict@1234';

    /**
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, string $name, string $email, Role $role, array $scope, ?string $idNumber = null): User
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

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'id_number' => $idNumber,
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            // The admin vouches for the address — approvers are trusted
            // accounts and must not hit the email/account verification walls
            // before they can log in.
            'email_verified_at' => now(),
            'account_status' => AccountStatus::Verified,
        ]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role' => $role,
            'school_id' => $scope['school_id'] ?? null,
            'program_id' => $scope['program_id'] ?? null,
            'organization_id' => $scope['organization_id'] ?? null,
        ]);

        try {
            $user->notify(new ApproverProvisionedNotification($role, self::DEFAULT_PASSWORD));
        } catch (\Throwable $e) {
            Log::error('Approver-provisioned notification failed to dispatch', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return $user;
    }

    /**
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     *
     * @throws ValidationException
     */
    private function guardScopeMatchesRole(Role $role, array $scope): void
    {
        $expectedKey = match ($role->scopeType()) {
            ScopeType::Organization => 'organization_id',
            ScopeType::Program => 'program_id',
            ScopeType::School => 'school_id',
            ScopeType::Global => null,
        };

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
