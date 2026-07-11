<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
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

/**
 * Builds and submits a registration document directly for an org the actor
 * is ALREADY bound to (not via SubmitOrganizationRegistration, which now
 * requires a not-yet-affiliated founding student — Phase 2 item 5). This
 * test is about index() visibility by org membership, not submission
 * mechanics; the founding student's own-pending-proposal visibility is
 * covered separately in OrganizationFoundingTest.
 */
function submitRegistrationFor(User $actor, Organization $org): void
{
    $document = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $actor->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $document->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'A student organization.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09171234567',
        'email_address' => 'contact@example.test',
        'date_organized' => '2024-06-01',
        'adviser_id' => null,
    ]);
    app(ApprovalEngine::class)->submit($document, $actor);
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
