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
        // Group D item 1: the client's real form asks for Overall Goal and
        // Specific Objectives separately — the single `objectives` textarea
        // was never split. Nullable at the DB level; required only via
        // SubmitProposalRequest/UpdateActivityProposalRequest for real HTTP
        // submissions, same deliberate exception used for every prior
        // slice's fields.
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('overall_goal')->nullable()->after('objectives');
            $table->text('specific_objectives')->nullable()->after('overall_goal');
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('objectives');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('objectives')->nullable()->after('title');
        });

        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['overall_goal', 'specific_objectives']);
        });
    }
};
