<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Officer term history. `is_active` REMAINS the sole discriminator for
     * "currently holds office" — these two columns are pure history, never a
     * read predicate. Three legitimate states going forward:
     *   is_active=true,  ended_at=null      -> current officer
     *   is_active=false, ended_at=<instant> -> past term, real recorded end
     *   is_active=false, ended_at=null      -> past term, end date not
     *                                          recorded (legacy rows only,
     *                                          deactivated before this
     *                                          migration existed)
     * The last state is deliberately NOT backfilled with a guessed date —
     * there is no reliable way to know exactly when a pre-existing
     * deactivated row's term really ended, and a fabricated date would look
     * like real history. `started_at` IS safely backfilled below: every
     * membership row is created synchronously at the moment of binding, so
     * created_at already equals the term's real start for every existing
     * row.
     *
     * `timestamp`, not `date` — these are system-recorded instants (stored
     * UTC, like every other timestamp in this app), not user-entered
     * calendar values. A `date` column would bake in the wrong Asia/Manila
     * calendar day for any turnover between UTC midnight and 08:00 UTC.
     *
     * Also adds a partial unique index so the database itself rejects a
     * second simultaneously-active holder of the same organization/position
     * seat — closing a real gap the new admin-finalized officer-change queue
     * makes easier to hit than the single-adviser-screen path ever was.
     */
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('is_active');
            $table->timestamp('ended_at')->nullable()->after('started_at');
        });

        DB::table('organization_memberships')
            ->whereNull('started_at')
            ->update(['started_at' => DB::raw('created_at')]);

        // Laravel's Blueprint has no portable fluent API for a partial
        // (conditional) index, so this is raw DDL. Both Postgres and SQLite
        // support "WHERE is_active" verbatim (a boolean column used as its
        // own truthy predicate), which keeps the same statement working
        // against the sqlite :memory: test database and Postgres in
        // production.
        DB::statement('create unique index org_memberships_one_active_per_seat on organization_memberships (organization_id, position) where is_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index if exists org_memberships_one_active_per_seat');

        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at']);
        });
    }
};
