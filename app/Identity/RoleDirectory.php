<?php

namespace App\Identity;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Read-only service that resolves a role to the person(s) holding it, scoped
 * to the relevant school or program. The approval engine (Slice 1) consumes
 * this exclusively — never resolve role → person outside this class.
 */
class RoleDirectory
{
    /**
     * @throws ModelNotFoundException
     */
    public function adviserFor(Organization $organization): User
    {
        return $this->resolveOrgScoped(Role::Adviser, $organization);
    }

    /**
     * Whether the given user is the adviser of this organization. The single
     * adviser-ownership check — used both by BindOrganizationOfficer (binding
     * officers) and DocumentPolicy::manageOfficers (deactivating them), so
     * it lives here once rather than being duplicated in each caller.
     */
    public function isAdviserOf(User $user, Organization $organization): bool
    {
        try {
            return $this->adviserFor($organization)->id === $user->id;
        } catch (ModelNotFoundException) {
            return false;
        }
    }

    /**
     * Only valid for regular-school organizations (those with a program).
     *
     * @throws ModelNotFoundException|\LogicException
     */
    public function programChairFor(Organization $organization): User
    {
        if ($organization->belongsToSeniorHighSchool()) {
            throw new \LogicException('Senior High School organizations have no program chair.');
        }

        return RoleAssignment::query()
            ->where('role', Role::ProgramChair)
            ->where('program_id', $organization->program_id)
            ->firstOrFail()
            ->user;
    }

    /**
     * Only valid for regular-school organizations.
     *
     * @throws ModelNotFoundException|\LogicException
     */
    public function deanFor(Organization $organization): User
    {
        if ($organization->belongsToSeniorHighSchool()) {
            throw new \LogicException('Senior High School has no dean.');
        }

        return $this->resolveSchoolScoped(Role::Dean, $organization->school_id);
    }

    /**
     * Only valid for SHS organizations.
     *
     * @throws ModelNotFoundException|\LogicException
     */
    public function principalFor(Organization $organization): User
    {
        if (! $organization->belongsToSeniorHighSchool()) {
            throw new \LogicException('Only Senior High School organizations have a principal.');
        }

        return $this->resolveSchoolScoped(Role::Principal, $organization->school_id);
    }

    /**
     * Returns both SDAO members. Exactly two must exist.
     *
     * @return Collection<int, User>
     */
    public function sdaoMembers(): Collection
    {
        return User::query()
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))
            ->get();
    }

    /** @throws ModelNotFoundException */
    public function assistantDirectorAcademicServices(): User
    {
        return $this->resolveGlobal(Role::AssistantDirectorAcademicServices);
    }

    /** @throws ModelNotFoundException */
    public function academicDirector(): User
    {
        return $this->resolveGlobal(Role::AcademicDirector);
    }

    /** @throws ModelNotFoundException */
    public function executiveDirector(): User
    {
        return $this->resolveGlobal(Role::ExecutiveDirector);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** @throws ModelNotFoundException */
    private function resolveOrgScoped(Role $role, Organization $organization): User
    {
        return RoleAssignment::query()
            ->where('role', $role)
            ->where('organization_id', $organization->id)
            ->firstOrFail()
            ->user;
    }

    /** @throws ModelNotFoundException */
    private function resolveSchoolScoped(Role $role, int $schoolId): User
    {
        return RoleAssignment::query()
            ->where('role', $role)
            ->where('school_id', $schoolId)
            ->firstOrFail()
            ->user;
    }

    /** @throws ModelNotFoundException */
    private function resolveGlobal(Role $role): User
    {
        return RoleAssignment::query()
            ->where('role', $role)
            ->firstOrFail()
            ->user;
    }
}
