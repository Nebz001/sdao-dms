<?php

use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\OrganizationMembershipService;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Covers the term-history columns added to organization_memberships
 * (started_at/ended_at) — is_active REMAINS the sole discriminator for
 * "currently holds this seat"; these two columns are pure history and must
 * never become a read predicate. See the migration's docblock for the
 * three legitimate states this locks in.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->bindAction = app(BindOrganizationOfficer::class);
    $this->membershipService = app(OrganizationMembershipService::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('adviser turnover stamps ended_at on the outgoing row and started_at on the incoming row, exactly equal', function () {
    $oldMembership = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::President->value)
        ->where('is_active', true)
        ->firstOrFail();

    $newStudent = User::factory()->create();
    $newMembership = $this->bindAction->execute($this->adviserOne, $this->org, $newStudent, OfficerPosition::President);

    $oldMembership->refresh();
    expect($oldMembership->is_active)->toBeFalse();
    expect($oldMembership->ended_at)->not->toBeNull();
    expect($newMembership->started_at)->not->toBeNull();
    expect($newMembership->ended_at)->toBeNull();
    expect($oldMembership->ended_at->equalTo($newMembership->started_at))->toBeTrue();
});

test('HTTP officer deactivation (Manage Officers) stamps ended_at', function () {
    $membership = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::President->value)
        ->where('is_active', true)
        ->firstOrFail();

    $this->actingAs($this->adviserOne)->delete(route('officers.destroy', [$this->org, $membership]));

    $membership->refresh();
    expect($membership->is_active)->toBeFalse();
    expect($membership->ended_at)->not->toBeNull();
});

test('deactivating an already-inactive membership never overwrites its real recorded ended_at', function () {
    $membership = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::President->value)
        ->where('is_active', true)
        ->firstOrFail();

    $this->membershipService->close($membership);
    $membership->refresh();
    $firstEndedAt = $membership->ended_at;

    // Re-deactivating the same (now-inactive) row must be a no-op, not a
    // second stamp — the service guard is the one place this is enforced.
    $this->membershipService->close($membership);
    $membership->refresh();

    expect($membership->ended_at->equalTo($firstEndedAt))->toBeTrue();
});

test('scopeActive/hasActiveMembershipElsewhere still key off is_active alone — never switched to reading ended_at', function () {
    // A hand-crafted row that is somehow active with a past ended_at
    // (should never happen in practice, but proves the read predicate).
    $weirdOrg = Organization::factory()->create();
    $weirdStudent = User::factory()->create();
    $weirdMembership = OrganizationMembership::create([
        'user_id' => $weirdStudent->id,
        'organization_id' => $weirdOrg->id,
        'position' => OfficerPosition::President->value,
        'academic_year' => '2024-2025',
        'is_active' => true,
        'started_at' => now()->subYear(),
        'ended_at' => now()->subMonths(6),
    ]);

    expect(OrganizationMembership::query()->active()->whereKey($weirdMembership->id)->exists())->toBeTrue();
    expect($this->membershipService->hasActiveMembershipElsewhere($weirdStudent))->toBeTrue();
});

test('a legacy row (is_active=false, ended_at=null) stays excluded from every active read', function () {
    $legacyOrg = Organization::factory()->create();
    $legacyStudent = User::factory()->create();
    $legacyMembership = OrganizationMembership::create([
        'user_id' => $legacyStudent->id,
        'organization_id' => $legacyOrg->id,
        'position' => OfficerPosition::President->value,
        'academic_year' => '2023-2024',
        'is_active' => false,
        'started_at' => now()->subYears(2),
        'ended_at' => null,
    ]);

    expect(OrganizationMembership::query()->active()->whereKey($legacyMembership->id)->exists())->toBeFalse();
    expect($this->membershipService->hasActiveMembershipElsewhere($legacyStudent))->toBeFalse();
});

test('the database rejects a second simultaneously-active holder of the same organization/position seat', function () {
    $secondStudent = User::factory()->create();

    expect(fn () => DB::table('organization_memberships')->insert([
        'user_id' => $secondStudent->id,
        'organization_id' => $this->org->id,
        'position' => OfficerPosition::President->value,
        'academic_year' => '2025-2026',
        'is_active' => true,
        'started_at' => now(),
        'ended_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
