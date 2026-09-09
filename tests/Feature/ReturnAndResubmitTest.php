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
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
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
    $engine->submit($doc, $submitter);
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
        purposeOfOrganization: 'Updated description.',
        contactPerson: 'Student Alpha',
        contactNo: '09171234567',
        emailAddress: 'cs@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $resubmitted->refresh();
    expect($resubmitted->status)->toBe(DocumentStatus::InReview);
    expect($resubmitted->current_step_position)->toBe(1);
    expect($resubmitted->registrationDetail->purpose_of_organization)->toBe('Updated description.');
});

// Structural fix (2026-09-09 plan): organization_type is derived from the
// org's school_id, computed once at creation and never written again —
// execute() no longer even accepts it as input. Computing Society has a
// school, so its detail was seeded CoCurricular by returnedRegistration();
// resubmitting must leave it exactly that, regardless of anything else about
// the request. This directly replaces the old assertion above (which used to
// prove the opposite — that resubmit COULD flip organization_type freely).
test('resubmitting a returned registration never changes organization_type', function () {
    $doc = returnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA);
    expect($doc->registrationDetail->organization_type)->toBe(OrganizationType::CoCurricular);

    $resubmitted = $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        purposeOfOrganization: 'Updated description.',
        contactPerson: 'Student Alpha',
        contactNo: '09171234567',
        emailAddress: 'cs@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    expect($resubmitted->registrationDetail->organization_type)->toBe(OrganizationType::CoCurricular);
});

// Decision 2 (2026-09-09 plan): historical organization_type values are NOT
// corrected, deliberately — revision history stays faithful to what was
// recorded, and nothing functional depends on it (routing already reads
// Organization::hasNoSchool() directly, never this column). This is the
// positive expression of that decision: a document whose stored
// organization_type already disagrees with its org's actual school_id (the
// exact kind of pre-existing drift the 2026_09_09_100000 migration corrected
// at the org level, one layer up) is left exactly as-is by an ordinary
// resubmit — nothing in the application ever reads it back and rewrites it.
test('a pre-existing organization_type that disagrees with the org\'s school_id is left untouched by resubmit', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id, // Computing Society — has a school
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        // Deliberately drifted: this org HAS a school, but the stored value
        // says Extra-Curricular — simulating a historical row from before
        // this fix, or a subsequent org-level correction like Red Cross
        // Youth's.
        'organization_type' => OrganizationType::ExtraCurricular,
    ]);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please revise.');
    $doc->refresh();

    $resubmitted = $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        purposeOfOrganization: 'Updated description.',
        contactPerson: 'Student Alpha',
        contactNo: '09171234567',
        emailAddress: 'cs@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    // Still drifted — untouched, exactly as decided.
    expect($resubmitted->registrationDetail->organization_type)->toBe(OrganizationType::ExtraCurricular);
});

test('resubmit resumes at SDAO step and both must re-approve', function () {
    $doc = returnedRegistration($this->org, $this->engine, $this->studentAlpha, $this->sdaoA);

    // Resubmit.
    $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        purposeOfOrganization: 'Revised.',
        contactPerson: 'Alpha',
        contactNo: '09171234567',
        emailAddress: 'cs@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
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
        purposeOfOrganization: 'Malicious edit.',
        contactPerson: 'Outsider',
        contactNo: '123',
        emailAddress: 'x@x.com',
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
        purposeOfOrganization: 'test',
        contactPerson: 'Test',
        contactNo: '123',
        emailAddress: 'test@test.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});
