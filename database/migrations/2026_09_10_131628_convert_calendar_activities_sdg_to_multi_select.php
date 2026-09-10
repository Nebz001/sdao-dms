<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group B item 1 — the "SDG" field on the Activity Calendar becomes
     * multi-select, mirroring how `activity_proposals.partner_organizations`
     * already stores a set of values: a `json` column, one Sdg value per
     * array element.
     *
     * Existing rows are NOT dropped. A four-step add/backfill/drop/rename —
     * not a bare column-type change — because the existing `sdg` column
     * holds a plain enum string ("no_poverty"), which is not valid JSON on
     * its own (an unquoted bare word); a straight ALTER COLUMN ... TYPE json
     * would reject that data outright on Postgres. Same cross-driver-safe,
     * PHP-side-derivation precedent as
     * 2026_09_05_100100_backfill_period_and_coverage_on_organization_registration_details_table
     * and 2026_09_10_092831_null_school_and_program_on_rejected_type_change —
     * stays exercisable under sqlite, where a bare type change on an
     * existing column isn't meaningful anyway (sqlite has no native json
     * type; Laravel's json() is just a TEXT column there).
     */
    public function up(): void
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->json('sdg_multi')->nullable()->after('sdg');
        });

        DB::table('calendar_activities')
            ->whereNotNull('sdg')
            ->orderBy('id')
            ->select('id', 'sdg')
            ->each(function (object $row): void {
                DB::table('calendar_activities')
                    ->where('id', $row->id)
                    ->update(['sdg_multi' => json_encode([$row->sdg])]);
            });

        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropColumn('sdg');
        });

        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->renameColumn('sdg_multi', 'sdg');
        });
    }

    /**
     * Deliberately a no-op, same honesty as the migrations referenced above:
     * a multi-value row (two or more SDGs) has no lossless single-value
     * representation to revert to.
     */
    public function down(): void
    {
        //
    }
};
