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
        Schema::table('organization_registration_details', function (Blueprint $table) {
            // Null for original registration rows; set to the renewal's academic year
            // (e.g. "2026-2027") when this detail belongs to an OrganizationRenewal document.
            $table->string('academic_year')->nullable()->after('roster');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
    }
};
