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
    private bool $memoizing = false;

    /** @var array<string, User|Collection<int, User>> */
    private array $memo = [];

    /**
     * Turn on request-scoped memoization for the duration of the callback —
     * every seat resolveScoped()/resolveGlobal()/sdaoMembers() looks up
     * underneath is cached by its own scope key, so resolving the same seat
     * (e.g. the same organization's adviser) across many documents in one
     * queue build costs one query instead of one per document.
     *
     * Off by default: a lookup made outside this wrapper always hits the
     * database, exactly as before this existed. Bound `scoped` in
     * AppServiceProvider so the same instance — and so the same cache — is
     * shared by every consumer (DocumentPolicy, StepApproverResolver, the
     * queue controller) within one request; a fresh request always gets a
     * fresh instance and an empty cache.
     *
     * A failed lookup (ModelNotFoundException) is never cached — only a
     * successful resolution is — so a misconfigured seat is re-checked on
     * every call, never permanently denied for the rest of the request.
     */
    public function remembering(callable $callback): mixed
    {
        $wasMemoizing = $this->memoizing;
        $this->memoizing = true;

        try {
            return $callback();
        } finally {
            $this->memoizing = $wasMemoizing;

            if (! $this->memoizing) {
                $this->memo = [];
            }
        }
    }

    private function remember(string $key, callable $resolve): mixed
    {
        if (! $this->memoizing) {
            return $resolve();
        }

        return $this->memo[$key] ??= $resolve();
    }

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
     * Only valid for regular-school organizations (those with both a school
     * and a program) — enforced below, not just documented: a school with no
     * program is a data-integrity violation (see the 2026_09_09_100000
     * migration), and must fail loudly here rather than reach firstOrFail()
     * and 404 silently.
     *
     * @throws ModelNotFoundException|\LogicException
     */
    public function programChairFor(Organization $organization): User
    {
        if ($organization->hasNoSchool()) {
            throw new \LogicException("Organization {$organization->id} ({$organization->name}) is Extra-Curricular with no college — cannot resolve a program chair.");
        }

        if ($organization->belongsToSeniorHighSchool()) {
            throw new \LogicException("Organization {$organization->id} ({$organization->name}) is Senior High School — has no program chair.");
        }

        if ($organization->program_id === null) {
            throw new \LogicException("Organization {$organization->id} ({$organization->name}) has a school but no program — cannot resolve a program chair. This indicates a data-integrity violation.");
        }

        return $this->resolveScoped(Role::ProgramChair, 'program_id', $organization->program_id);
    }

    /**
     * Only valid for regular-school organizations (those with a school).
     *
     * @throws ModelNotFoundException|\LogicException
     */
    public function deanFor(Organization $organization): User
    {
        if ($organization->hasNoSchool()) {
            throw new \LogicException('An Extra-Curricular organization with no college has no dean.');
        }

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
     * Returns every user holding the SDAO member role. The dual-approval
     * step (invariant #3) only ever requires 2 of them to approve — that
     * required count lives on WorkflowStep::required_approvals, not on how
     * many SDAO accounts happen to exist. In the test suite this can be
     * more than 2 (the real roster can coexist with the fixture's placeholder
     * SDAO accounts); a dev or demo database has exactly the two real members.
     *
     * @return Collection<int, User>
     */
    public function sdaoMembers(): Collection
    {
        return $this->remember(
            'sdao_members',
            fn () => User::query()
                ->active()
                ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::SdaoMember))
                ->get(),
        );
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
        return $this->resolveScoped($role, 'organization_id', $organization->id);
    }

    /** @throws ModelNotFoundException */
    private function resolveSchoolScoped(Role $role, int $schoolId): User
    {
        return $this->resolveScoped($role, 'school_id', $schoolId);
    }

    /**
     * Scoped counterpart to resolveGlobal(): more than one row for a
     * single-holder scoped role (one adviser per organization, one chair
     * per program, one dean/principal per school) is a data-quality bug
     * this method cannot repair — it only guarantees the choice is STABLE,
     * favoring the FIRST-assigned holder, identically to resolveGlobal()'s
     * rationale below. Stability is the point: ApprovalEngine::activateStep()
     * (who gets notified) and DocumentPolicy::review()/isChainApprover()
     * (who is authorized to open the document) both resolve the same seat
     * independently, at different moments — any disagreement between the
     * two is exactly what produces a notified approver getting a 403 on
     * their own notification link. Duplicates are prevented going forward
     * by Admin\ProvisionApprover::retireIncumbent() and cleaned up
     * historically by the 2026_09_17_100000 migration.
     *
     * @throws ModelNotFoundException
     */
    private function resolveScoped(Role $role, string $scopeColumn, int $scopeId): User
    {
        return $this->remember(
            "scoped:{$role->value}:{$scopeColumn}:{$scopeId}",
            fn () => RoleAssignment::query()
                ->where('role', $role)
                ->where($scopeColumn, $scopeId)
                ->whereHas('user', fn ($q) => $q->active())
                ->oldest('id')
                ->firstOrFail()
                ->user,
        );
    }

    /**
     * More than one row for a single-holder global role (Assistant/Academic/
     * Executive Director) is a data-quality bug this method cannot repair —
     * it only guarantees the choice is stable, favoring the FIRST-assigned
     * holder. This is deliberate, not arbitrary: the intentional roster
     * seeder (RealRosterSeeder) always runs before the placeholder fixture
     * (IdentitySeeder) in every documented combined-seed path, so the
     * earliest assignment is the real one. See
     * Admin\ProvisionApprover::execute(), which replaces a single-holder
     * global role's assignment in place rather than appending — a duplicate
     * can only originate from history or from re-running seeders against a
     * persisted database, never from normal admin provisioning going forward.
     *
     * @throws ModelNotFoundException
     */
    private function resolveGlobal(Role $role): User
    {
        return $this->remember(
            "global:{$role->value}",
            fn () => RoleAssignment::query()
                ->where('role', $role)
                ->whereHas('user', fn ($q) => $q->active())
                ->oldest('id')
                ->firstOrFail()
                ->user,
        );
    }
}
