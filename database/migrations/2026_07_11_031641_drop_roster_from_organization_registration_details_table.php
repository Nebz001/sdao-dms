<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 item 8: `roster` never appeared in sdao.md's exact-fields list
     * for Registration/Renewal (unlike every other field, sourced verbatim
     * from the client's physical form) and had zero data-entry UI. The
     * "Updated List of Officers/Founders" attachment (new in this item)
     * supersedes it as the real mechanism.
     */
    public function up(): void
    {
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->dropColumn('roster');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->json('roster')->nullable()->after('adviser_id');
        });
    }
};
