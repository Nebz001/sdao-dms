<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specific Objectives: one objective per line, stored as plain text with
 * newlines and numbered only when displayed. Nullable at the DB level and at
 * validation: the field is optional, and the existing single `objectives`
 * field is untouched.
 *
 * Created as `objective_lines`; the next migration renames it to
 * `specific_objectives`, the one name used everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('objective_lines')->nullable()->after('objectives');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('objective_lines');
        });
    }
};
