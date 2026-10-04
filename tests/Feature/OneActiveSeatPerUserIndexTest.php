<?php

use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * org_memberships_one_active_seat_per_user — the database backstop for the
 * one-organization-per-student rule (a student holds at most one active seat).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President, Computing Society
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society
});

function insertActiveSeat(User $user, Organization $org, string $position): void
{
    OrganizationMembership::create([
        'user_id' => $user->id,
        'organization_id' => $org->id,
        'position' => $position,
        'academic_year' => '2026-2027',
        'is_active' => true,
        'started_at' => now(),
    ]);
}

function seatPerUserIndexExists(): bool
{
    return collect(Schema::getIndexes('organization_memberships'))->pluck('name')->contains('org_memberships_one_active_seat_per_user');
}

function loadIndexMigration(): object
{
    return require glob(database_path('migrations/*_add_one_active_seat_per_user_index_to_organization_memberships.php'))[0];
}

test('the database refuses a second active seat for the same student — another org, or the other seat in the same org', function () {
    // Savepoints: Postgres aborts the surrounding transaction on a violation.
    expect(fn () => DB::transaction(fn () => insertActiveSeat($this->president, $this->itGuild, 'secretary')))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(fn () => DB::transaction(fn () => insertActiveSeat($this->president, $this->org, 'secretary')))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('closed seats never count for the index', function () {
    $former = User::factory()->create(['account_status' => 'verified']);

    foreach (['president', 'secretary'] as $position) {
        OrganizationMembership::create([
            'user_id' => $former->id, 'organization_id' => $this->itGuild->id, 'position' => $position,
            'academic_year' => '2025-2026', 'is_active' => false, 'started_at' => now()->subYear(), 'ended_at' => now()->subMonth(),
        ]);
    }
    // Free Computing Society's secretary seat, then bind the former officer into it.
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);

    insertActiveSeat($former, $this->org, 'secretary');

    expect(OrganizationMembership::where('user_id', $former->id)->active()->count())->toBe(1);
    expect(OrganizationMembership::where('user_id', $former->id)->count())->toBe(3);
});

test('a bind that loses to a rival claiming the same STUDENT elsewhere gets the friendly message, not a 500', function () {
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);

    // The rival lands an active seat for the SAME student in a different org in
    // the window between this bind's guards and its insert — only the new
    // per-student index can stop that (the per-seat index sees a different seat).
    $fired = false;
    OrganizationMembership::creating(function (OrganizationMembership $m) use (&$fired, $nominee) {
        if ($fired || $m->user_id !== $nominee->id) {
            return;
        }
        $fired = true;
        DB::table('organization_memberships')->insert([
            'user_id' => $nominee->id, 'organization_id' => $this->itGuild->id, 'position' => 'secretary',
            'academic_year' => '2026-2027', 'is_active' => true, 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    expect(fn () => app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $nominee, OfficerPosition::Secretary))
        ->toThrow(ValidationException::class, 'Someone else just changed this seat');

    // Rolled back as a unit — the president was not disturbed.
    expect(OrganizationMembership::where('organization_id', $this->org->id)->where('position', 'president')->active()->pluck('user_id')->all())
        ->toBe([$this->president->id]);
});

test('over HTTP the lost per-student race is a form error, not a 500', function () {
    $this->withoutVite();
    $nominee = User::factory()->create(['account_status' => 'verified']);
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);

    $fired = false;
    OrganizationMembership::creating(function (OrganizationMembership $m) use (&$fired, $nominee) {
        if ($fired || $m->user_id !== $nominee->id) {
            return;
        }
        $fired = true;
        DB::table('organization_memberships')->insert([
            'user_id' => $nominee->id, 'organization_id' => $this->itGuild->id, 'position' => 'secretary',
            'academic_year' => '2026-2027', 'is_active' => true, 'started_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    });

    $this->actingAs($this->adviser)
        ->from(route('officers.index', $this->org))
        ->post(route('officers.store', $this->org), ['user_id' => $nominee->id, 'position' => 'secretary'])
        ->assertRedirect(route('officers.index', $this->org))
        ->assertSessionHasErrors('user_id');
});

test('the migration refuses to run while any student holds more than one active seat, naming them, and changes nothing', function () {
    $migration = loadIndexMigration();
    $migration->down();

    insertActiveSeat($this->president, $this->itGuild, 'secretary'); // Alpha: 2 active seats
    $other = User::factory()->create(['account_status' => 'verified']);
    DB::table('organization_memberships')->where('user_id', User::where('email', 'student-beta@students.nu-lipa.edu.ph')->value('id'))->update(['is_active' => false, 'ended_at' => now()]);
    insertActiveSeat($other, $this->itGuild, 'president');
    $this->secretary->organizationMemberships()->active()->update(['is_active' => false, 'ended_at' => now()]);
    insertActiveSeat($other, $this->org, 'secretary'); // other: 2 active seats

    $before = OrganizationMembership::query()->orderBy('id')->get(['id', 'user_id', 'is_active', 'ended_at'])->toArray();

    try {
        $migration->up();
        $this->fail('The migration should have thrown.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('2 student(s)')
            ->toContain("user {$this->president->id} (2 active seats)")
            ->toContain("user {$other->id} (2 active seats)")
            ->toContain('Nothing was changed');
    }

    // No guessing: not a single row was touched, and the index was not created.
    expect(OrganizationMembership::query()->orderBy('id')->get(['id', 'user_id', 'is_active', 'ended_at'])->toArray())->toBe($before);
    expect(seatPerUserIndexExists())->toBeFalse();
});

test('on a clean database the migration is a plain no-op followed by the index', function () {
    $migration = loadIndexMigration();
    $migration->down();
    $before = OrganizationMembership::query()->orderBy('id')->get()->toArray();

    $migration->up();

    expect(OrganizationMembership::query()->orderBy('id')->get()->toArray())->toBe($before);
    expect(seatPerUserIndexExists())->toBeTrue();
    expect(fn () => DB::transaction(fn () => insertActiveSeat($this->president, $this->itGuild, 'secretary')))
        ->toThrow(UniqueConstraintViolationException::class);
});
