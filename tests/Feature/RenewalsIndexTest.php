<?php

use App\Approval\ApprovalEngine;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use App\Renewals\SubmitOrganizationRenewal;
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

function submitRenewalFor(User $actor, Organization $org): void
{
    $engine = app(ApprovalEngine::class);
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();

    $registration = app(SubmitOrganizationRegistration::class)->execute(
        actor: $actor,
        organization: $org,
        organizationType: OrganizationType::CoCurricular,
        description: 'Original description.',
        contactPerson: 'Original Person',
        contactNumber: '09171111111',
        contactEmail: 'original@example.test',
        dateOrganized: '2020-06-01',
        roster: ['Member One'],
    );
    $engine->approve($registration, $sdaoA);
    $registration->refresh();
    $engine->approve($registration, $sdaoB);
    $registration->refresh();

    app(SubmitOrganizationRenewal::class)->execute(
        actor: $actor,
        organization: $org,
        organizationType: OrganizationType::CoCurricular,
        description: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNumber: '09172222222',
        contactEmail: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        roster: ['Member One'],
    );
}

test('officer sees their org renewal in the index', function () {
    submitRenewalFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('renewals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/index')
            ->has('renewals', 1)
            ->where('renewals.0.organization.name', 'Computing Society')
        );
});

test('both president and secretary of the same org see the same renewal', function () {
    submitRenewalFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentDelta)
        ->withoutVite()
        ->get(route('renewals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/index')
            ->has('renewals', 1)
        );
});

test('an org officer does not see another org\'s renewal', function () {
    submitRenewalFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentBeta)
        ->withoutVite()
        ->get(route('renewals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/index')
            ->has('renewals', 0)
        );
});
