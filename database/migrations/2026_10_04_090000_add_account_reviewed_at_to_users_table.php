<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_reviewed_at')->nullable();
        });

        // Accounts decided before this column existed have no recorded
        // decision time. updated_at is the closest thing there is, but it
        // moves on ANY edit (a name change, a password reset), so these
        // backfilled values are approximate. Only rows stamped from now on
        // by VerifyAccount / RejectAccount are exact.
        DB::table('users')
            ->whereIn('account_status', ['verified', 'rejected'])
            ->update(['account_reviewed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_reviewed_at');
        });
    }
};
