<?php

use App\Enums\AccountStatus;
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

// Regression: this is the real-world scenario behind the "officer search
// doesn't work" QA report. The query itself was always correct — an
// unverified self-registered student is correctly excluded until SDAO
// verifies them — but nothing previously exercised that exact transition
// (existing tests above use User::factory()->create(), whose default
// account_status is Verified). Proves both halves explicitly: excluded
// while Unverified, found immediately once Verified.
test('an unverified self-registered student is not findable until SDAO verifies them', function () {
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $freshStudent = User::factory()->unverifiedAccount()->create([
        'name' => 'Just Registered',
        'email' => 'just-registered@example.test',
    ]);

    // Not yet findable — correctly excluded while Unverified.
    $this->actingAs($this->adviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org).'?search=just-registered@example.test')
        ->assertInertia(fn ($page) => $page->has('students', 0));

    // SDAO verifies the account via the real Pending Accounts flow.
    $this->actingAs($sdaoA)->post(route('admin.pending-accounts.verify', $freshStudent));
    expect($freshStudent->fresh()->account_status)->toBe(AccountStatus::Verified);

    // Now findable — the same search, no other change.
    $this->actingAs($this->adviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org).'?search=just-registered@example.test')
        ->assertInertia(fn ($page) => $page
            ->has('students', 1)
            ->where('students.0.email', 'just-registered@example.test')
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

// ── officers.index / officers.store authorization (Security gap fix) ───────
//
// index() and store() previously carried no authorization call at all —
// only destroy() had a Gate::authorize. index() was genuinely exploitable
// (roster + student-directory PII disclosure to ANY authenticated,
// email-verified user); store() was already blocked indirectly by
// BindOrganizationOfficer::execute()'s own isAdviserOf() check, but lacked
// the controller-level gate destroy() has, so the rule lived in two places.
// These tests lock all three actions to the org's own adviser via the same
// `manageOfficers` gate.

test('a plain student cannot view, search, or bind officers for an org they are not the adviser of', function () {
    // student-alpha is an active officer of Computing Society, not IT Guild —
    // an authenticated, legitimate account with zero relationship to IT Guild.
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $target = User::factory()->create();

    $this->actingAs($student)
        ->withoutVite()
        ->get(route('officers.index', $this->itGuild))
        ->assertForbidden();

    $this->actingAs($student)
        ->post(route('officers.store', $this->itGuild), [
            'user_id' => $target->id,
            'position' => OfficerPosition::Secretary->value,
        ])
        ->assertForbidden();

    expect(OrganizationMembership::where('user_id', $target->id)->exists())->toBeFalse();

    $itGuildMembership = OrganizationMembership::where('organization_id', $this->itGuild->id)
        ->where('is_active', true)
        ->firstOrFail();

    $this->actingAs($student)
        ->delete(route('officers.destroy', [$this->itGuild, $itGuildMembership]))
        ->assertForbidden();

    expect($itGuildMembership->fresh()->is_active)->toBeTrue();
});

test('a DIFFERENT org\'s adviser cannot view, search, or bind officers for this org', function () {
    $itGuildAdviser = User::where('email', 'adviser-two@sdao.test')->firstOrFail();
    $target = User::factory()->create();

    $this->actingAs($itGuildAdviser)
        ->withoutVite()
        ->get(route('officers.index', $this->org))
        ->assertForbidden();

    $this->actingAs($itGuildAdviser)
        ->post(route('officers.store', $this->org), [
            'user_id' => $target->id,
            'position' => OfficerPosition::Secretary->value,
        ])
        ->assertForbidden();

    expect(OrganizationMembership::where('user_id', $target->id)->exists())->toBeFalse();

    $orgMembership = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('is_active', true)
        ->firstOrFail();

    $this->actingAs($itGuildAdviser)
        ->delete(route('officers.destroy', [$this->org, $orgMembership]))
        ->assertForbidden();

    expect($orgMembership->fresh()->is_active)->toBeTrue();
});

// ── officers.destroy cross-org IDOR (audit finding, not in the original report) ──
//
// Gate::authorize('manageOfficers', $organization) proves the actor advises
// $organization, but never checked that $membership actually BELONGS to
// $organization — the two route-bound models were independently resolved.
// An adviser could pair their OWN org with a foreign membership ID and
// deactivate another organization's officer. 404 (not 403) is correct here:
// the actor is legitimately authorized for the org they passed, so the
// failure is "no such membership under this org," not "forbidden."

test('an adviser cannot deactivate another org\'s officer by pairing their own org with a foreign membership id', function () {
    // student-alpha is Computing Society's seeded active President (MembershipSeeder).
    $foreignMembership = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('is_active', true)
        ->where('position', OfficerPosition::President->value)
        ->firstOrFail();

    $itGuildAdviser = User::where('email', 'adviser-two@sdao.test')->firstOrFail();

    $response = $this->actingAs($itGuildAdviser)
        ->delete(route('officers.destroy', [$this->itGuild, $foreignMembership]));

    $response->assertNotFound();
    expect($foreignMembership->fresh()->is_active)->toBeTrue();
});
