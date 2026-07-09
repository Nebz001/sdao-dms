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
        Schema::table('users', function (Blueprint $table) {
            // Defaults to 'unverified' (fail closed): CreateNewUser sets this
            // explicitly on self-registration; ProvisionApprover and the
            // UserFactory set 'verified' explicitly for admin-provisioned /
            // seeded accounts. Indexed — the Pending Accounts queue filters on it.
            $table->string('account_status')->default('unverified')->after('email_verified_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_status');
        });
    }
};
