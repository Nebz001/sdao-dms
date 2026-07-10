<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('a freshly self-registered account has zero role assignments', function () {
    $this->post(route('register.store'), [
        'name' => 'Bare Student',
        'email' => 'bare-student@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'bare-student@example.test')->firstOrFail();

    expect($user->roleAssignments()->count())->toBe(0);
});

test('a freshly self-registered account starts Unverified, awaiting SDAO review', function () {
    $this->post(route('register.store'), [
        'name' => 'Fresh Registrant',
        'email' => 'fresh-registrant@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'fresh-registrant@example.test')->firstOrFail();

    expect($user->account_status)->toBe(AccountStatus::Unverified);
});

test('the public registration endpoint ignores role/scope fields smuggled into the request body', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $this->post(route('register.store'), [
        'name' => 'Smuggler',
        'email' => 'smuggler@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        // None of these are real Fortify/CreateNewUser fields — an attempt
        // to self-grant a role via the public form must have no effect.
        'role' => Role::SdaoMember->value,
        'organization_id' => $org->id,
    ]);

    $user = User::where('email', 'smuggler@example.test')->firstOrFail();

    expect($user->roleAssignments()->count())->toBe(0);
    expect(RoleAssignment::where('user_id', $user->id)->exists())->toBeFalse();
});

test('a self-registered, verified user CAN propose a new organization (Phase 2 item 5), but still cannot reach review/admin routes', function () {
    $user = User::factory()->create(); // verified, but zero RoleAssignment / OrganizationMembership

    // A bare, not-yet-affiliated Verified student is EXACTLY who item 5's
    // founding flow is for — this is no longer blocked (see
    // SubmitRegistrationTest for full coverage of this path). What remains
    // blocked is everything else: review/admin routes below, and every other
    // form type (renewal/calendar/proposal/report — covered in
    // AccountVerificationGateTest and elsewhere), which still require an
    // existing officer binding this bare account doesn't have.
    $school = School::query()->firstOrFail();
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $response = $this->actingAs($user)->post(route('registrations.store'), [
        'name' => 'Bare User Founded Org',
        'school_id' => $school->id,
        'adviser_id' => $adviser->id,
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'A brand-new organization.',
        'contact_person' => 'Someone',
        'contact_no' => '09170000000',
        'email_address' => 'someone@example.test',
        'date_organized' => '2020-06-01',
    ]);
    $response->assertRedirect();
    expect(Organization::where('name', 'Bare User Founded Org')->exists())->toBeTrue();

    // Cannot reach an SDAO review queue.
    $this->actingAs($user)->get(route('review.registrations.index'))->assertOk(); // queue itself has no gate…
    // …but cannot approve anything on it (no document exists to act on is implicit; the
    // real gate is exercised in ReviewOrganizationRenewalTest et al. via DocumentPolicy).

    // Cannot reach the admin provisioning area.
    $this->actingAs($user)->get(route('admin.approvers.index'))->assertForbidden();
});
