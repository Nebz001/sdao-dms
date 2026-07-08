<?php

namespace App\Identity\Admin;

use App\Enums\Role;
use App\Enums\ScopeType;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SDAO-admin account creation for approvers (adviser, program chair, dean,
 * principal, SDAO member, the three directors). Approvers are never
 * self-registered (CLAUDE.md "Identity & accounts") — this is the only
 * production code path (besides seeders) that creates an approver account.
 *
 * The new account gets no password from the admin: it's created with a
 * random, unusable one and the approver sets their own via the existing
 * Fortify password-reset flow (no new invite/mail infrastructure).
 */
class ProvisionApprover
{
    /**
     * @param  array{school_id?: int|null, program_id?: int|null, organization_id?: int|null}  $scope
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, string $name, string $email, Role $role, array $scope): User
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
            'password' => Hash::make(Str::random(40)),
        ]);

        RoleAssignment::create([
            'user_id' => $user->id,
            'role' => $role,
            'school_id' => $scope['school_id'] ?? null,
            'program_id' => $scope['program_id'] ?? null,
            'organization_id' => $scope['organization_id'] ?? null,
        ]);

        Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $email]);

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
