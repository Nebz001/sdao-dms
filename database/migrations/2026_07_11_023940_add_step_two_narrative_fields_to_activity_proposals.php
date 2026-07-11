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
        // Phase 2 item 7 slice 4b: exact field corrections for the Proposal
        // Narrative (step 2). Nullable at the DB level; required only via
        // SubmitProposalRequest/UpdateActivityProposalRequest for real HTTP
        // submissions — same deliberate exception used for every prior
        // slice's new fields (see e.g. CalendarActivity's migration comment).
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('criteria_mechanics')->nullable()->after('narrative'); // "Criteria/Mechanics"
            $table->text('program_flow')->nullable()->after('criteria_mechanics'); // "Program Flow"
            $table->text('source_of_funding')->nullable()->after('program_flow'); // "Source of Funding"
            $table->text('expenses')->nullable()->after('source_of_funding'); // "Expenses"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['criteria_mechanics', 'program_flow', 'source_of_funding', 'expenses']);
        });
    }
};
