<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 item 9 — structured revision-flag data on a return transition.
     * Nullable: reject and every non-return transition never set this.
     * Registration/Renewal/Activity Proposal/After-Activity Report use static
     * registry keys (App\Approval\SectionFlags); Activity Calendar uses
     * "activity_{index}" (0-based row position at time of return — row ids
     * aren't stable across resubmit, since UpdateActivityCalendar deletes and
     * recreates all CalendarActivity rows on every resubmit).
     */
    public function up(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->json('flagged_sections')->nullable()->after('comment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->dropColumn('flagged_sections');
        });
    }
};
