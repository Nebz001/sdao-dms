<?php

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@sdao.test')->firstOrFail(); // secretary, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // president, IT Guild
});

function submitRegistrationFor(User $actor, Organization $org): void
{
    app(SubmitOrganizationRegistration::class)->execute(
        actor: $actor,
        organization: $org,
        organizationType: OrganizationType::CoCurricular,
        description: 'A student organization.',
        contactPerson: 'Contact Person',
        contactNumber: '09171234567',
        contactEmail: 'contact@example.test',
        dateOrganized: '2024-06-01',
        roster: ['Member One'],
    );
}

test('officer sees their org registration in the index', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations', 1)
            ->where('registrations.0.organization.name', 'Computing Society')
        );
});

test('both president and secretary of the same org see the same registration', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentDelta)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations', 1)
        );
});

test('an org officer does not see another org\'s registration', function () {
    submitRegistrationFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentBeta)
        ->withoutVite()
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/index')
            ->has('registrations', 0)
        );
});
