<?php

use App\Enums\FormType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enforces, as data, an invariant `StoreRegistrationRequest` already
     * enforces at the HTTP layer: an Extra-Curricular organization is
     * university-wide and has no college, so its `school_id`/`program_id` must
     * both be null. Some existing organizations violate this because the
     * validator can be bypassed by a caller that constructs a registration
     * directly (proven by `DemoDataSeeder`, which is how these rows were
     * created) — see the fix plan for the resulting silent 404 this caused at
     * program-chair approval.
     *
     * For each organization, find its MOST-RECENT registration or renewal
     * detail row — of ANY document status, ordered by document id descending
     * — and null both columns if that row says `extra_curricular` and either
     * column is currently set. Both "any status" and "most-recent only" are
     * deliberate:
     *
     * - Any status, not just Approved: a registration can be In Review at
     *   migration time (e.g. NEXUS) — an Approved-only rule would leave a
     *   timebomb that reproduces this exact bug the moment SDAO approves it.
     * - Most-recent only, not any matching row: `organization_type` is a
     *   user-editable field on renewal (`StoreRenewalRequest`), so an org's
     *   type can legitimately change between records (confirmed live: Red
     *   Cross Youth has an approved extra_curricular registration and a
     *   rejected co_curricular renewal). Filtering on any historical row
     *   would wrongly null an org that is Co-Curricular now.
     *
     * No Senior High School carve-out: an Extra-Curricular org with
     * `school_id` pointing at Senior High School is nulled the same as any
     * other violation — `StoreRegistrationRequest` already rejects that
     * combination for new registrations, so this is not a new rule, only data
     * catching up to it.
     *
     * `school_id`/`program_id` are immutable after creation (confirmed: the
     * only write in the app is `SubmitOrganizationRegistration::execute()` at
     * creation time), so nulling them on a Draft/InReview/Rejected
     * registration cannot race against or undo any other write.
     *
     * Derivation is done in PHP, not SQL, so it stays exercisable under
     * sqlite in tests — same precedent as
     * 2026_09_05_100100_backfill_period_and_coverage_on_organization_registration_details_table.
     */
    public function up(): void
    {
        $mostRecentDetailIds = DB::table('organization_registration_details as ord')
            ->join('documents as d', 'd.id', '=', 'ord.document_id')
            ->whereIn('d.form_type', [
                FormType::OrganizationRegistration->value,
                FormType::OrganizationRenewal->value,
            ])
            ->orderBy('d.organization_id')
            ->orderByDesc('d.id')
            ->get(['d.organization_id', 'd.id', 'ord.id as detail_id', 'ord.organization_type']);

        $orgIdsToNull = $mostRecentDetailIds
            ->unique('organization_id')
            ->where('organization_type', 'extra_curricular')
            ->pluck('organization_id');

        DB::table('organizations')
            ->whereIn('id', $orgIdsToNull)
            ->where(fn ($q) => $q->whereNotNull('school_id')->orWhereNotNull('program_id'))
            ->update(['school_id' => null, 'program_id' => null]);
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op: the original school_id/program_id values this
     * migration nulls are not recoverable (they were never captured
     * anywhere), so there is nothing to restore — same honesty as
     * 2026_09_05_100100_backfill_period_and_coverage_on_organization_registration_details_table's
     * down().
     */
    public function down(): void
    {
        //
    }
};
