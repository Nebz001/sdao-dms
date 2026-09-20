<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Submission-time snapshot of "Prepared by: [PRESIDENT'S FULL NAME]" —
     * replaces a live query against the org's currently-active president
     * (which made a reprinted old proposal show whoever holds the office
     * TODAY, not who held it when the proposal was actually submitted).
     * Deliberately NOT backfilled: there is no reliable way to know who was
     * president when an already-existing proposal was submitted, so those
     * proposals print a blank "Prepared by" line rather than a guessed name
     * presented as accurate history. See App\ActivityProposals\SubmitActivityProposal
     * (writes it) and App\Printing\ActivityProposalForm (reads it, no fallback).
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->string('president_name')->nullable()->after('responsible_persons');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('president_name');
        });
    }
};
