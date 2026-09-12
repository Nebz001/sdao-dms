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
        // Group D item 4: `source_of_funding` (step 2, free text) duplicated
        // `budget_source` (step 1, closed dropdown since Group C item 2) —
        // nothing in the app ever treated them as different questions. Step
        // 2 now just echoes budget_source_label read-only instead of asking
        // again.
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('source_of_funding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('source_of_funding')->nullable()->after('program_flow');
        });
    }
};
