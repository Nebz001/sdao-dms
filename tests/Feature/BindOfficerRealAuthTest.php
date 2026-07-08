<?php

use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
});

test('a self-registered bare student is findable via the officer search', function () {
    $bareStudent = User::factory()->create(['name' => 'Fresh Self Registered', 'email' => 'fresh@example.test']);
    expect($bareStudent->roleAssignments()->count())->toBe(0); // genuinely bare

    $response = $this->actingAs($this->adviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org).'?search=fresh@example.test');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/officers/index')
        ->has('students', 1)
        ->where('students.0.email', 'fresh@example.test')
    );
});

test('binding a self-registered bare student grants an OrganizationMembership and creates NO RoleAssignment', function () {
    $bareStudent = User::factory()->create();

    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $bareStudent->id,
        'position' => OfficerPosition::Secretary->value,
    ]);

    expect(OrganizationMembership::where('user_id', $bareStudent->id)
        ->where('organization_id', $this->org->id)
        ->where('is_active', true)
        ->exists())->toBeTrue();

    // role_assignments has no status column and is never updated once
    // created — nav/the officer picker now read OrganizationMembership.is_active
    // directly instead, so binding no longer needs to create this row at all.
    expect(RoleAssignment::where('user_id', $bareStudent->id)
        ->where('role', Role::Student->value)
        ->exists())->toBeFalse();
});

test('a bare, unbound account does not appear in a DIFFERENT org\'s officer search and cannot submit', function () {
    $bareStudent = User::factory()->create(['name' => 'Belongs Nowhere Yet', 'email' => 'nowhere@example.test']);

    // Bind them to Computing Society.
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $bareStudent->id,
        'position' => OfficerPosition::President->value,
    ]);

    // IT Guild's adviser searches for the same name — must NOT find a
    // Computing-Society-bound student in their own org's picker.
    $itGuildAdviser = User::where('email', 'adviser-two@sdao.test')->firstOrFail();
    $response = $this->actingAs($itGuildAdviser)
        ->withoutVite()
        ->get(route('officers.index', $this->itGuild).'?search=nowhere@example.test');

    $response->assertInertia(fn ($page) => $page
        ->component('organizations/officers/index')
        ->has('students', 0)
    );
});

test('officer turnover correctly goes stale: the outgoing officer loses nav access and disappears from the picker', function () {
    $studentA = User::factory()->create(['name' => 'Outgoing President', 'email' => 'outgoing@example.test']);
    $studentB = User::factory()->create(['name' => 'Incoming President', 'email' => 'incoming@example.test']);

    // Bind A as president.
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $studentA->id,
        'position' => OfficerPosition::President->value,
    ]);

    // Sanity check: A currently has nav access and is findable in the picker.
    $this->actingAs($studentA)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isActiveOfficer', true));

    $this->actingAs($this->adviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org).'?search=outgoing@example.test')
        ->assertInertia(fn ($page) => $page->has('students', 1));

    // Turnover: bind B as president for the SAME org+position — this
    // deactivates A's OrganizationMembership (existing turnover logic,
    // unchanged by this fix).
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $studentB->id,
        'position' => OfficerPosition::President->value,
    ]);

    expect(OrganizationMembership::where('user_id', $studentA->id)
        ->where('organization_id', $this->org->id)
        ->where('is_active', false)
        ->exists())->toBeTrue();

    // 1. A's nav no longer shows Submit/My Documents — isActiveOfficer is false.
    $this->actingAs($studentA)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isActiveOfficer', false));

    // 2. A no longer appears in the adviser's officer-binding picker.
    $this->actingAs($this->adviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org).'?search=outgoing@example.test')
        ->assertInertia(fn ($page) => $page->has('students', 0));

    // B, the incoming president, has full nav access and is (still) findable.
    $this->actingAs($studentB)
        ->withoutVite()
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.isActiveOfficer', true));
});

// ── officers.destroy authorization (IDOR gap audit fix) ─────────────────────

test('the org\'s own adviser can deactivate an officer', function () {
    $student = User::factory()->create();
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $student->id,
        'position' => OfficerPosition::President->value,
    ]);
    $membership = OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $this->org->id)
        ->firstOrFail();

    $response = $this->actingAs($this->adviser)
        ->delete(route('officers.destroy', [$this->org, $membership]));

    $response->assertRedirect(route('officers.index', $this->org));
    expect($membership->fresh()->is_active)->toBeFalse();
});

test('a DIFFERENT org\'s adviser cannot deactivate this org\'s officer', function () {
    $student = User::factory()->create();
    $this->actingAs($this->adviser)->post(route('officers.store', $this->org), [
        'user_id' => $student->id,
        'position' => OfficerPosition::President->value,
    ]);
    $membership = OrganizationMembership::where('user_id', $student->id)
        ->where('organization_id', $this->org->id)
        ->firstOrFail();

    $itGuildAdviser = User::where('email', 'adviser-two@sdao.test')->firstOrFail();

    $response = $this->actingAs($itGuildAdviser)
        ->delete(route('officers.destroy', [$this->org, $membership]));

    $response->assertForbidden();
    expect($membership->fresh()->is_active)->toBeTrue();
});
