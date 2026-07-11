<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
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

    // Built directly (not via SubmitOrganizationRegistration) — this fixture
    // only needs a prior Approved registration for an org the actor is
    // already bound to; registration-submission mechanics live elsewhere.
    $registration = Document::create([
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
        'document_id' => $registration->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Original Person',
        'contact_no' => '09171111111',
        'email_address' => 'original@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $engine->submit($registration, $actor);
    $registration->refresh();
    $engine->approve($registration, $sdaoA);
    $registration->refresh();
    $engine->approve($registration, $sdaoB);
    $registration->refresh();

    app(SubmitOrganizationRenewal::class)->execute(
        actor: $actor,
        organization: $org,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
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
