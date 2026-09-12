<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restores "Activity Description" as a real, intentional field (client
     * request) — under a new column name, not a revival of the old
     * `narrative` column dropped in 2026_09_11_144855. That migration's data
     * is not recovered here; this is a fresh, empty column.
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->text('activity_description')->nullable()->after('objectives');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn('activity_description');
        });
    }
};
