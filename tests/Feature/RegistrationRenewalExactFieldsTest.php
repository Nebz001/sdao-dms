<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 7 slice 2 — exact field corrections for Registration/Renewal:
 * full-stack renames (contact_no, email_address, purpose_of_organization),
 * the College relabel (School, unchanged schema), Type of Organization enum
 * wording, and Organization Name/College/Program field-presence parity
 * across every rendering.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->program = Program::where('name', 'BS Computer Science')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
});

function unboundAdviserForFieldsTest(): User
{
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    return $adviser;
}

function registrationStorePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Exact Fields Test Org',
        'school_id' => null, // set by caller
        'program_id' => null,
        'adviser_id' => null, // set by caller
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Testing exact field corrections.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09171234567',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => registrationAttachmentFiles(),
    ], $overrides);
}

// --- Validation messages read using the client's real-form wording ------

test('registration store validation messages use the exact client wording for renamed fields', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), [
        'name' => 'Missing Fields Org',
        'school_id' => $this->school->id,
        'adviser_id' => unboundAdviserForFieldsTest()->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'date_organized' => '2020-06-01',
        // purpose_of_organization, contact_no, email_address all omitted
    ]);

    $response->assertInvalid([
        'purpose_of_organization' => 'Purpose of Organization',
        'contact_no' => 'Contact No.',
        'email_address' => 'Email Address',
    ]);
});

// --- Full round-trip: submit -> stored -> shown with exact field names --

test('registration submission stores and displays the renamed fields correctly', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForFieldsTest();

    $response = $this->actingAs($student)->post(route('registrations.store'), registrationStorePayload([
        'school_id' => $this->school->id,
        'program_id' => $this->program->id,
        'adviser_id' => $adviser->id,
    ]));

    $response->assertRedirect();

    $org = Organization::where('name', 'Exact Fields Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();
    $detail = $document->registrationDetail;

    expect($detail->purpose_of_organization)->toBe('Testing exact field corrections.');
    expect($detail->contact_no)->toBe('09171234567');
    expect($detail->email_address)->toBe('contact@example.test');

    // Type of Organization enum labels match the exact client wording.
    expect($detail->organization_type->label())->toBe('Co-Curricular');
    expect(OrganizationType::ExtraCurricular->label())->toBe('Extra Curricular-Interest Clubs');

    $this->actingAs($student)
        ->withoutVite()
        ->get(route('registrations.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/show')
            ->where('detail.purpose_of_organization', 'Testing exact field corrections.')
            ->where('detail.contact_no', '09171234567')
            ->where('detail.email_address', 'contact@example.test')
            ->where('detail.organization_type_label', 'Co-Curricular')
            ->where('document.organization.college', 'School of Computing and IT')
            ->where('document.organization.program', 'BS Computer Science')
        );
});

// --- Field-presence parity: Organization Name / College / Program -------

test('College and Program are present on every rendering, not just registration create', function () {
    // Approve a founding registration so we can exercise edit/renewal/review pages too.
    $student = User::factory()->create();
    $adviser = unboundAdviserForFieldsTest();

    $this->actingAs($student)->post(route('registrations.store'), registrationStorePayload([
        'school_id' => $this->school->id,
        'program_id' => $this->program->id,
        'adviser_id' => $adviser->id,
    ]));
    $org = Organization::where('name', 'Exact Fields Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();

    // review/registrations/show — approver view.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.registrations.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('document.organization.college', 'School of Computing and IT')
            ->where('document.organization.program', 'BS Computer Science')
        );

    // Return it so we can hit the registration edit page.
    $this->actingAs($this->sdaoA)
        ->post(route('review.registrations.return', $document), ['comment' => 'Fix something.']);

    $this->actingAs($student)
        ->withoutVite()
        ->get(route('registrations.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('document.organization.college', 'School of Computing and IT')
            ->where('document.organization.program', 'BS Computer Science')
        );
});

test('a Senior High School organization shows College with no Program (no program to show)', function () {
    $shsOrg = Organization::where('name', 'SHS Student Council')->firstOrFail();
    $shsStudent = User::where('email', 'student-gamma@sdao.test')->firstOrFail();

    $renewalAction = app(SubmitOrganizationRenewal::class);
    $engine = app(ApprovalEngine::class);

    // Give it a prior Approved registration so renewal's precondition is met.
    $registration = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$shsOrg->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $shsOrg->id,
        'workflow_template_id' => null,
        'submitted_by' => $shsStudent->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $registration->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'SHS org.',
        'contact_person' => 'SHS Contact',
        'contact_no' => '09170000000',
        'email_address' => 'shs@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $engine->submit($registration, $shsStudent);
    $registration->refresh();
    $engine->approve($registration, $this->sdaoA);
    $registration->refresh();
    $engine->approve($registration, $this->sdaoB);

    $renewal = $renewalAction->execute(
        actor: $shsStudent,
        organization: $shsOrg,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'SHS renewal.',
        contactPerson: 'SHS Contact',
        contactNo: '09170000000',
        emailAddress: 'shs@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );

    $this->actingAs($shsStudent)
        ->withoutVite()
        ->get(route('renewals.show', $renewal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('document.organization.college', 'Senior High School')
            ->where('document.organization.program', null)
        );
});
