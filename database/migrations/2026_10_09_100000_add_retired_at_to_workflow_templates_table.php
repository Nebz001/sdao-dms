<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A retired template stays in the table so every document already routed
     * through it keeps reading its own steps (history labels, wait stats and
     * approval rows all key off the template's step positions), but it is
     * never picked for a new submission.
     */
    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
    }
};
