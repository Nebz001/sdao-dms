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
        // Phase 2 item 7 slice 3: exact field corrections.
        Schema::table('after_activity_reports', function (Blueprint $table) {
            $table->renameColumn('narrative', 'summary'); // "Summary"
        });

        Schema::table('after_activity_reports', function (Blueprint $table) {
            // Nullable at the DB level; required only via StoreReportRequest/
            // UpdateReportRequest for real HTTP submissions — same deliberate
            // exception as Slice 1's calendar-activity fields, to avoid
            // forcing every direct-action-call test to supply them.
            $table->json('activity_chairs')->nullable()->after('summary'); // "Activity Chair/s"
            $table->string('prepared_by')->nullable()->after('activity_chairs'); // "Prepared By"
            $table->text('event_program')->nullable()->after('prepared_by'); // "Program"
            $table->unsignedTinyInteger('target_participants_percentage')->nullable()->after('event_program'); // "% target participants" (0-100)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('after_activity_reports', function (Blueprint $table) {
            $table->dropColumn(['activity_chairs', 'prepared_by', 'event_program', 'target_participants_percentage']);
        });

        Schema::table('after_activity_reports', function (Blueprint $table) {
            $table->renameColumn('summary', 'narrative');
        });
    }
};
