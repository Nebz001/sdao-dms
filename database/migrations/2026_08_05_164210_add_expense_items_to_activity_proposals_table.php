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
        // Itemized "Expenses" (client request, post-Part-2): a structured
        // list of {label, amount} rows replacing the free-text description
        // going forward. The old `expenses` text column is kept, untouched,
        // as a read-only fallback for the handful of pre-existing proposals
        // that only ever had prose — no backfill, see ActivityProposalForm.
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->json('expense_items')->nullable()->after('expenses'); // [{label, amount}, ...]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('expense_items');
        });
    }
};
