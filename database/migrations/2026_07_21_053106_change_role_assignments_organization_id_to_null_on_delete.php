<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deleting an organization must free its adviser back to the available
     * pool, not destroy the adviser role entirely — an adviser's
     * organization_id should become null, mirroring a fresh, unbound
     * provisioning (Phase 2 item 5), rather than cascade-deleting the
     * role_assignments row.
     */
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }
};
