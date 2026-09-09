<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * /organizations/mine — the caller's own organization. No Gate: the query
 * itself (the caller's active OrganizationMembership) is the authorization
 * boundary, per DocumentHistoryController's query-scope-as-authorization
 * idiom.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(); // President, IT Guild
});

test('an officer sees their own organization', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()
        ->get(route('organizations.mine'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/mine')
            ->where('organization.id', $this->computingSociety->id)
            ->where('organization.name', 'Computing Society')
            ->has('status')
            ->has('requirements')
        );
});

test('an officer never sees a different organization\'s data', function () {
    $this->actingAs($this->studentBeta)->withoutVite()
        ->get(route('organizations.mine'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organization.id', $this->itGuild->id)
            ->where('organization.name', 'IT Guild')
        );
});

test('a user with no active organization membership is redirected to the dashboard', function () {
    $bareUser = User::factory()->create();

    $this->actingAs($bareUser)->withoutVite()
        ->get(route('organizations.mine'))
        ->assertRedirect(route('dashboard'));
});
