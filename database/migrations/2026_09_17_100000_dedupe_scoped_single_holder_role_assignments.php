<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin\ProvisionApprover::execute() only ever retired a previous holder's
 * role_assignments row for the three GLOBAL single-holder roles (Assistant/
 * Academic/Executive Director) — every scope-bound single-holder role
 * (Adviser once org-bound, Program Chair, Dean, Principal) fell into a plain
 * create(), so reassigning one of these left two live rows for the same
 * role+scope. RoleDirectory then resolved "who holds this role" via an
 * unordered firstOrFail(), so which of the two rows won was undefined — and
 * ApprovalEngine::activateStep() (who gets notified) and
 * DocumentPolicy::review()/isChainApprover() (who is authorized) could
 * resolve that ambiguity differently, producing a notified approver who
 * gets a 403 on their own notification link. ProvisionApprover::
 * retireIncumbent() and RoleDirectory::resolveScoped() fix this going
 * forward (see both); this migration is the one-time cleanup for a database
 * that already has a duplicate.
 *
 * Survivor rule: lowest id (first-assigned) wins, identically to
 * RoleDirectory::resolveScoped()'s and resolveGlobal()'s ordering — so the
 * account this migration keeps live is the same one the app will resolve to
 * afterwards.
 *
 * Deliberately excludes: SdaoMember and the three Director roles (global
 * multiplicity/replace-in-place is resolveGlobal()'s concern, untouched
 * here), Student (many per organization, by design), and any Adviser row
 * with a NULL organization_id (the admin-provisioned available pool — see
 * ProvisionApprover::guardScopeMatchesRole()'s docblock — is never a
 * contested "seat" and must never be touched). Derivation is done in PHP
 * rather than SQL window functions so this stays exercisable under sqlite
 * in tests, mirroring 2026_08_29_120000_dedupe_schools_and_add_unique_name_index.php.
 *
 * No unique index is added: one would break the intentional SdaoMember
 * multiplicity, the null-scope Adviser pool, and one user legitimately
 * chairing two different programs (see RealRosterSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeBySchoolOrProgram(Role::Dean->value, 'school_id');
        $this->dedupeBySchoolOrProgram(Role::Principal->value, 'school_id');
        $this->dedupeBySchoolOrProgram(Role::ProgramChair->value, 'program_id');
        $this->dedupeAdviser();
    }

    public function down(): void
    {
        // One-time data cleanup, not a reversible structural change — which
        // row lost (or, for Adviser, which row's organization_id was nulled)
        // is not recorded anywhere, and recreating an ambiguous duplicate
        // would only reintroduce the bug this migration fixes.
    }

    /**
     * Dean/Principal (school-scoped) and Program Chair (program-scoped):
     * a scope-less row is meaningless for these roles (their scope key is
     * always required — see ProvisionApprover::guardScopeMatchesRole()), so
     * every loser is simply deleted.
     */
    private function dedupeBySchoolOrProgram(string $role, string $column): void
    {
        $duplicateScopeValues = DB::table('role_assignments')
            ->select($column)
            ->where('role', $role)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->pluck($column);

        foreach ($duplicateScopeValues as $scopeValue) {
            $ids = DB::table('role_assignments')
                ->where('role', $role)
                ->where($column, $scopeValue)
                ->orderBy('id')
                ->pluck('id');

            $loserIds = $ids->slice(1)->values();

            DB::table('role_assignments')->whereIn('id', $loserIds)->delete();
        }
    }

    /**
     * Adviser (organization-scoped): a superseded holder is freed back to
     * the available pool (organization_id => null), never deleted — see
     * ProvisionApprover::retireIncumbent()'s docblock for why (the
     * Store/UpdateRegistrationRequest `exists` validation and
     * ApproveOrganizationRegistration's user_id-only lookup both depend on
     * every adviser account keeping exactly one lifetime row). The one
     * exception: if a loser belongs to the SAME user as the survivor (an
     * exact duplicate of one person's own binding, not a genuine
     * reassignment), it is deleted instead — unbinding it would just leave
     * that one person with two adviser rows, the same ambiguity this
     * migration is cleaning up.
     */
    private function dedupeAdviser(): void
    {
        $role = Role::Adviser->value;

        $duplicateOrgIds = DB::table('role_assignments')
            ->select('organization_id')
            ->where('role', $role)
            ->whereNotNull('organization_id')
            ->groupBy('organization_id')
            ->havingRaw('count(*) > 1')
            ->pluck('organization_id');

        foreach ($duplicateOrgIds as $organizationId) {
            $rows = DB::table('role_assignments')
                ->where('role', $role)
                ->where('organization_id', $organizationId)
                ->orderBy('id')
                ->get(['id', 'user_id']);

            $survivor = $rows->first();
            $losers = $rows->slice(1);

            $sameUserLoserIds = $losers->where('user_id', $survivor->user_id)->pluck('id');
            $otherUserLoserIds = $losers->where('user_id', '!=', $survivor->user_id)->pluck('id');

            DB::table('role_assignments')->whereIn('id', $sameUserLoserIds)->delete();

            DB::table('role_assignments')->whereIn('id', $otherUserLoserIds)
                ->update(['organization_id' => null, 'updated_at' => now()]);
        }
    }
};
