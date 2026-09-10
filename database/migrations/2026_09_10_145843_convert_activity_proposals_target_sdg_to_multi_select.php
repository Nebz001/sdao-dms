<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group C item 1 — "Target SDG" becomes multi-select, identical shape to
     * 2026_09_10_131628_convert_calendar_activities_sdg_to_multi_select
     * (calendar_activities.sdg): a `json` column, one Sdg value per array
     * element, via the same add/backfill/drop/rename sequence rather than a
     * bare column-type change — the existing `target_sdg` column holds a
     * plain enum string ("no_poverty"), which isn't valid JSON on its own
     * (an unquoted bare word), so a straight ALTER COLUMN ... TYPE json would
     * reject that data outright on Postgres. Existing rows are NOT dropped.
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->json('target_sdg_multi')->nullable()->after('target_sdg');
        });

        DB::table('activity_proposals')
            ->whereNotNull('target_sdg')
            ->orderBy('id')
            ->select('id', 'target_sdg')
            ->each(function (object $row): void {
                DB::table('activity_proposals')
                    ->where('id', $row->id)
                    ->update(['target_sdg_multi' => json_encode([$row->target_sdg])]);
            });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('target_sdg');
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('target_sdg_multi', 'target_sdg');
        });
    }

    /**
     * Deliberately a no-op — a multi-value row has no lossless single-value
     * representation to revert to, same as the calendar migration it mirrors.
     */
    public function down(): void
    {
        //
    }
};
