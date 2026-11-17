<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One name for Specific Objectives: renames the existing `objective_lines`
 * column in place (no drop and recreate, so stored values are kept).
 *
 * The name `specific_objectives` was used once before, by a column that
 * 2026_09_12_060303_collapse_objectives_on_activity_proposals_table dropped.
 * That column is long gone by the time this runs (on a fresh database it was
 * added and dropped before this migration), so the name is free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('objective_lines', 'specific_objectives');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('specific_objectives', 'objective_lines');
        });
    }
};
