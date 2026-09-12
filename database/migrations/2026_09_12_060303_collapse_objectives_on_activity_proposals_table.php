<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Group E backlog — collapses `overall_goal` + `specific_objectives` (split
 * out in migration 2026_09_11_144853) back into a single `objectives`
 * field. Both hint phrases now live as placeholder text inside that one
 * field on the frontend instead of two separate inputs — see step-two.tsx
 * and edit.tsx.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('objectives')->nullable()->after('specific_objectives');
        });

        // Preserve any existing drafts/submissions rather than silently
        // emptying them — concatenate whichever of the two split values are
        // present, in the same order they appeared on the form.
        DB::table('activity_proposals')
            ->whereNotNull('overall_goal')
            ->orWhereNotNull('specific_objectives')
            ->orderBy('id')
            ->select('id', 'overall_goal', 'specific_objectives')
            ->get()
            ->each(function ($row) {
                $parts = array_filter([$row->overall_goal, $row->specific_objectives], fn ($v) => $v !== null && $v !== '');

                if ($parts !== []) {
                    DB::table('activity_proposals')
                        ->where('id', $row->id)
                        ->update(['objectives' => implode("\n\n", $parts)]);
                }
            });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['overall_goal', 'specific_objectives']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('overall_goal')->nullable()->after('objectives');
            $table->text('specific_objectives')->nullable()->after('overall_goal');
        });

        // Lossy on the way back — the concatenation above can't be split
        // apart again, so the combined text is restored into overall_goal
        // only, leaving specific_objectives blank for the operator to
        // re-split by hand if this rollback is ever actually needed.
        DB::table('activity_proposals')
            ->whereNotNull('objectives')
            ->update(['overall_goal' => DB::raw('objectives')]);

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('objectives');
        });
    }
};
