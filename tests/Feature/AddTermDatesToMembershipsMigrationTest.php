<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for the 2026_09_18_100000 migration. Reproduces the
 * pre-migration shape by rolling the migration back (dropping the columns)
 * and forward again (re-adding + backfilling them) against a row inserted
 * with no started_at/ended_at — the same shape every pre-existing
 * organization_memberships row had.
 */
function reapplyTermDatesMigration(): void
{
    $migration = require database_path('migrations/2026_09_18_100000_add_term_dates_to_organization_memberships_table.php');
    $migration->down();
    $migration->up();
}

test('started_at is backfilled from created_at for a pre-existing row', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $createdAt = now()->subDays(10);

    $membershipId = DB::table('organization_memberships')->insertGetId([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'position' => 'president',
        'academic_year' => '2025-2026',
        'is_active' => true,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    reapplyTermDatesMigration();

    $row = DB::table('organization_memberships')->find($membershipId);
    expect($row->started_at)->not->toBeNull();
    expect((string) $row->started_at)->toBe((string) $row->created_at);
});

test('a legacy deactivated row (is_active=false) keeps ended_at null — never fabricated', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    $membershipId = DB::table('organization_memberships')->insertGetId([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'position' => 'secretary',
        'academic_year' => '2024-2025',
        'is_active' => false,
        'created_at' => now()->subYear(),
        'updated_at' => now()->subMonths(6),
    ]);

    reapplyTermDatesMigration();

    $row = DB::table('organization_memberships')->find($membershipId);
    expect($row->started_at)->not->toBeNull();
    expect($row->ended_at)->toBeNull();
});
