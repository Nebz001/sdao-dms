<?php

use App\Enums\FormType;
use App\Enums\Term;
use App\Support\AcademicPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfills `term` and `covers_academic_year` on every existing
     * registration/renewal detail row, deriving the period each record was
     * created under from `documents.created_at` via
     * AcademicPeriod::forDate() — the same month-to-term map used everywhere
     * else a historical period must be inferred (see that method's docblock
     * for the provisional Aug-Nov/Dec-Mar/Apr-Jul boundaries).
     *
     * Every row (not just Approved ones) is backfilled: the eligibility gate
     * only reads Approved records, but leaving Draft/InReview/Returned rows
     * half-stamped would just invite a second backfill migration later.
     *
     * Derivation, in PHP rather than SQL so this stays exercisable under
     * sqlite in tests:
     * - Registration detail: covers = derived term is 3rd ? year+1 : year
     *   (the grace rule — an org founded during 3rd term is covered through
     *   the following year too).
     * - Renewal detail: covers = year+1, unconditionally. Going forward a
     *   renewal is only ever filed during 3rd term and always buys the next
     *   year; applying that same rule to historical renewals (which predate
     *   the season gate) keeps the meaning of `covers_academic_year`
     *   consistent for every renewal record, past or future.
     *
     * `academic_year` is written only where currently NULL — a renewal's
     * academic_year is deliberately frozen at creation and must never be
     * rewritten (see SubmitOrganizationRenewal/UpdateOrganizationRenewal).
     */
    public function up(): void
    {
        DB::table('organization_registration_details as detail')
            ->join('documents', 'documents.id', '=', 'detail.document_id')
            ->whereIn('documents.form_type', [
                FormType::OrganizationRegistration->value,
                FormType::OrganizationRenewal->value,
            ])
            ->orderBy('detail.id')
            ->select(['detail.id as detail_id', 'documents.form_type', 'documents.created_at', 'detail.academic_year'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $period = AcademicPeriod::forDate(Carbon::parse($row->created_at));
                    $isRenewal = $row->form_type === FormType::OrganizationRenewal->value;

                    $covers = $isRenewal
                        ? $period->nextAcademicYear()
                        : ($period->term === Term::ThirdTerm ? $period->nextAcademicYear() : $period->academicYear);

                    DB::table('organization_registration_details')
                        ->where('id', $row->detail_id)
                        ->update([
                            'term' => $period->term->value,
                            'covers_academic_year' => $covers,
                            'academic_year' => $row->academic_year ?? $period->academicYear,
                        ]);
                }
            }, 'detail.id', 'detail_id');
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately does NOT null out the backfilled `academic_year` values —
     * only the columns this migration is responsible for introducing the
     * meaning of (`term`, `covers_academic_year`) are cleared. Restoring
     * `academic_year` to its pre-backfill NULL state is a one-way door.
     */
    public function down(): void
    {
        DB::table('organization_registration_details')->update([
            'term' => null,
            'covers_academic_year' => null,
        ]);
    }
};
