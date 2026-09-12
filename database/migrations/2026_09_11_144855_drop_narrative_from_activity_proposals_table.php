<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Group D item 2: `narrative` is a leftover from the original
        // scaffold migration (before Criteria/Mechanics and Program Flow
        // were added by the exact-field-corrections pass) and never appears
        // in sdao.md's canonical step-2 field list — it was never
        // reconciled away. Criteria/Mechanics and Program Flow stay.
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('narrative');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('narrative')->nullable()->after('overall_goal');
        });
    }
};
