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
        // Phase 2 item 7 slice 2: exact field corrections — full-stack
        // rename to match the client's real physical/template form wording.
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->renameColumn('contact_number', 'contact_no'); // "Contact No."
            $table->renameColumn('contact_email', 'email_address'); // "Email Address"
            $table->renameColumn('description', 'purpose_of_organization'); // "Purpose of Organization"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_registration_details', function (Blueprint $table) {
            $table->renameColumn('contact_no', 'contact_number');
            $table->renameColumn('email_address', 'contact_email');
            $table->renameColumn('purpose_of_organization', 'description');
        });
    }
};
