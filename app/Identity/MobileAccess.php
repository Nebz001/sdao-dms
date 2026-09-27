<?php

namespace App\Identity;

use App\Enums\FormType;
use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Support\Collection;

/**
 * Derives mobile-approver eligibility from configuration — the step roles
 * that actually appear in the Activity Proposal workflow templates — never
 * a hardcoded role list. Adding a role to a template automatically makes it
 * an approver role here too, the same way the templates themselves are
 * configuration rather than code (CLAUDE.md invariant #1).
 */
class MobileAccess
{
    /**
     * Every role that appears as a step in any Activity Proposal workflow
     * template, across every variant.
     *
     * @return Collection<int, Role>
     */
    public function approverRoles(): Collection
    {
        return WorkflowStep::query()
            ->whereHas('template', fn ($q) => $q->where('form_type', FormType::ActivityProposal))
            ->get()
            ->pluck('role')
            ->unique(fn (Role $role) => $role->value)
            ->values();
    }

    /**
     * The user's own roles, intersected with the approver-role set above —
     * what the mobile app's `roles` field is built from.
     *
     * @return Collection<int, Role>
     */
    public function approverRolesFor(User $user): Collection
    {
        $approverRoles = $this->approverRoles();

        return $user->roleAssignments
            ->pluck('role')
            ->unique(fn (Role $role) => $role->value)
            ->filter(fn (Role $role) => $approverRoles->contains($role))
            ->values();
    }

    /**
     * Can this user use the mobile app at all? Email verified AND
     * account_status Verified AND at least one approver role — the single
     * predicate behind both `mobile_access` and `can_access_mobile_review`
     * in the /mobile/user response, and the `EnsureMobileAccess`
     * middleware, so the three can never disagree.
     */
    public function canAccess(User $user): bool
    {
        return $user->email_verified_at !== null
            && $user->isVerifiedAccount()
            && $this->approverRolesFor($user)->isNotEmpty();
    }
}
