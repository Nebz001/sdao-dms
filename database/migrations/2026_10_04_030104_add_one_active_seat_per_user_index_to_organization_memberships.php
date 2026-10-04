<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Database backstop for the one-organization-per-student rule: a student
     * holds at most ONE active officer seat in total (one org, one seat — which
     * also covers "never both President and Secretary"). Until now that rule
     * lived only in the application (OrganizationMembershipService::runSeatChange
     * plus the guards), with nothing in the database behind it. Same shape as
     * org_memberships_one_active_per_seat, keyed on the student instead.
     *
     * This migration NEVER guesses. If any student already holds more than one
     * active seat it fails, listing exactly who, so a human decides which seat
     * to close (and whether the older or the newer one is the real one) —
     * nothing is auto-closed, auto-declined or "resolved" by age. On a database
     * with no such student it is a plain no-op followed by the index creation.
     */
    public function up(): void
    {
        $offenders = DB::table('organization_memberships')
            ->where('is_active', true)
            ->select('user_id', DB::raw('count(*) as active_seats'))
            ->groupBy('user_id')
            ->having(DB::raw('count(*)'), '>', 1)
            ->orderBy('user_id')
            ->get();

        if ($offenders->isNotEmpty()) {
            $list = $offenders
                ->map(fn ($row) => "user {$row->user_id} ({$row->active_seats} active seats)")
                ->implode(', ');

            throw new RuntimeException(
                "Cannot enforce one active seat per student: {$offenders->count()} student(s) already hold more than one active officer seat — {$list}. "
                .'Close the extra seat(s) for each (set is_active = false and ended_at on the one that should end), then re-run this migration. Nothing was changed.'
            );
        }

        DB::statement('create unique index org_memberships_one_active_seat_per_user on organization_memberships (user_id) where is_active');
    }

    public function down(): void
    {
        DB::statement('drop index if exists org_memberships_one_active_seat_per_user');
    }
};
