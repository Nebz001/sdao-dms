<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address a student is actually mailed at. `email` stays the school
 * address: it is the login and the proof of identity (verification codes,
 * password reset, email verification). `personal_email` is where every other
 * notification goes, and is chosen in exactly one place, User::notificationEmail().
 *
 * Nullable and not unique on purpose: accounts that exist today (and every
 * approver, who has one address) have none and keep being mailed at `email`;
 * and a personal mailbox is not an identity, so it is not a login or a key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('personal_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('personal_email');
        });
    }
};
