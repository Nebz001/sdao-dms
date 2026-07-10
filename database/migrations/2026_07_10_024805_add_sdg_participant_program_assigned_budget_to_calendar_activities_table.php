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
        Schema::table('calendar_activities', function (Blueprint $table) {
            // Nullable at the DB level; required only via StoreActivityCalendarRequest/
            // UpdateActivityCalendarRequest for real HTTP submissions (Phase 2 item 7
            // slice 1) — a deliberate exception to this table's usual "DB NOT NULL
            // mirrors validation required" convention, to avoid forcing every
            // direct-action-call seeder/test to supply them.
            $table->string('sdg')->nullable()->after('description'); // Sdg enum
            $table->string('participant_program_assigned')->nullable()->after('sdg');
            $table->decimal('budget', 10, 2)->nullable()->after('participant_program_assigned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_activities', function (Blueprint $table) {
            $table->dropColumn(['sdg', 'participant_program_assigned', 'budget']);
        });
    }
};
