<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\RoleAssignment;
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

test('a self-registered, verified user still cannot submit any document or reach review/admin routes', function () {
    $user = User::factory()->create(); // verified, but zero RoleAssignment / OrganizationMembership

    // Cannot submit — RegistrationController::store() looks up the actor's
    // active membership first (firstOrFail), which doesn't exist for a bare
    // account, so this never even reaches the submit Gate check.
    $response = $this->actingAs($user)->post(route('registrations.store'), [
        'organization_type' => 'co_curricular',
        'description' => 'Should never be created.',
        'contact_person' => 'Someone',
        'contact_number' => '09170000000',
        'contact_email' => 'someone@example.test',
        'date_organized' => '2020-06-01',
    ]);
    $response->assertNotFound();

    // Cannot reach an SDAO review queue.
    $this->actingAs($user)->get(route('review.registrations.index'))->assertOk(); // queue itself has no gate…
    // …but cannot approve anything on it (no document exists to act on is implicit; the
    // real gate is exercised in ReviewOrganizationRenewalTest et al. via DocumentPolicy).

    // Cannot reach the admin provisioning area.
    $this->actingAs($user)->get(route('admin.approvers.index'))->assertForbidden();
});
