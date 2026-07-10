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
        // Phase 2 item 7 slice 4a: exact field corrections for the Activity
        // Request Form (proposal step 1).
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('estimated_budget', 'proposed_budget'); // "Proposed Budget"
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            // Nullable at the DB level; required only via
            // StoreProposalStepOneRequest for real HTTP submissions — same
            // deliberate exception used for every prior slice's new fields.
            $table->string('activity_nature')->nullable()->after('title'); // "Nature of Activity"
            $table->string('activity_type')->nullable()->after('activity_nature'); // "Type of Activity"
            $table->json('partner_organizations')->nullable()->after('activity_type'); // "Partner Organization(s)/School(s)/RSO"
            $table->string('target_sdg')->nullable()->after('partner_organizations'); // "Target SDG"
            $table->string('budget_source')->nullable()->after('proposed_budget'); // "Budget Source"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['activity_nature', 'activity_type', 'partner_organizations', 'target_sdg', 'budget_source']);
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->renameColumn('proposed_budget', 'estimated_budget');
        });
    }
};
