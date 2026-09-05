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
            // The term half of the period this record was stamped under
            // (academic_year is the year half). Null for pre-existing rows
            // until the companion backfill migration runs.
            $table->string('term')->nullable()->after('academic_year');

            // The academic year this record makes the organization active
            // for — distinct from `academic_year` (when the record was
            // stamped) because a 3rd-term approval/renewal covers the
            // FOLLOWING year (grace/renewal-season rule). An org is covered
            // for year Y iff it has an approved record with
            // covers_academic_year >= Y.
            $table->string('covers_academic_year')->nullable()->after('term');

            $table->index('covers_academic_year');
            $table->index(['academic_year', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->dropIndex(['covers_academic_year']);
            $table->dropIndex(['academic_year', 'term']);
            $table->dropColumn(['term', 'covers_academic_year']);
        });
    }
};
