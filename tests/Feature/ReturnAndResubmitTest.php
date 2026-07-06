<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Registrations\UpdateOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->updateAction = app(UpdateOrganizationRegistration::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function returnedRegistration(Organization $org, ApprovalEngine $engine, User $submitter, User $sdaoA): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $engine->submit($doc);
    $doc->refresh();
    $engine->returnForRevision($doc, $sdaoA, 'Please revise your description.');
    $doc->refresh();

    return $doc;
}

test('officer can edit and resubmit a returned registration', function () {
    $doc = returnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA);

    $resubmitted = $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        organizationType: OrganizationType::ExtraCurricular,
        description: 'Updated description.',
        contactPerson: 'Student Alpha',
        contactNumber: '09171234567',
        contactEmail: 'cs@sdao.test',
        dateOrganized: '2020-06-01',
        roster: ['Student Alpha'],
    );

    $resubmitted->refresh();
    expect($resubmitted->status)->toBe(DocumentStatus::InReview);
    expect($resubmitted->current_step_position)->toBe(1);
    expect($resubmitted->registrationDetail->organization_type)->toBe(OrganizationType::ExtraCurricular);
    expect($resubmitted->registrationDetail->description)->toBe('Updated description.');
});

test('resubmit resumes at SDAO step and both must re-approve', function () {
    $doc = returnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA);

    // Resubmit.
    $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        description: 'Revised.',
        contactPerson: 'Alpha',
        contactNumber: '09171234567',
        contactEmail: 'cs@sdao.test',
        dateOrganized: '2020-06-01',
    );
    $doc->refresh();

    // Partial approvals for step 1 were cleared on return.
    $partials = DocumentStepApproval::where('document_id', $doc->id)
        ->where('step_position', 1)
        ->count();
    expect($partials)->toBe(0);

    // Both must re-approve.
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
});

test('a different student cannot edit another student\'s returned registration', function () {
    $doc = returnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA);
    $outsider = User::factory()->create();

    expect(fn () => $this->updateAction->execute(
        actor: $outsider,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        description: 'Malicious edit.',
        contactPerson: 'Outsider',
        contactNumber: '123',
        contactEmail: 'x@x.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});

test('cannot update a document that is not Returned', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id]);

    expect(fn () => $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        description: 'test',
        contactPerson: 'Test',
        contactNumber: '123',
        contactEmail: 'test@test.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});
