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
        // Group E backlog — typed in directly by the submitting officer, not
        // sourced from org membership (see ActivityProposal's docblock).
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->json('responsible_persons')->nullable()->after('expense_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('responsible_persons');
        });
    }
};
