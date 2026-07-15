<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\UnauthorizedApproverException;
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
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->outsider = User::factory()->create();
});

/**
 * Builds an org with an Approved registration (so renewal's precondition is
 * met) and returns a freshly-submitted (InReview) renewal Document.
 */
function submittedRenewal(): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $engine = app(ApprovalEngine::class);

    // Built directly (not via SubmitOrganizationRegistration): this fixture
    // only needs a valid PRIOR APPROVED registration for an org the student
    // is already bound to (via MembershipSeeder) — registration-submission
    // mechanics are covered elsewhere (SubmitRegistrationTest /
    // OrganizationFoundingTest) and now require a not-yet-affiliated
    // founding student, the opposite of this fixture's shape.
    $registration = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $student->id,
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
    $engine->submit($registration, $student);
    $registration->refresh();
    $engine->approve($registration, $sdaoA);
    $registration->refresh();
    $engine->approve($registration, $sdaoB);
    $registration->refresh();

    return app(SubmitOrganizationRenewal::class)->execute(
        actor: $student,
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

test('first SDAO approve is partial — renewal stays InReview', function () {
    $doc = submittedRenewal();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
});

test('second SDAO approve completes the renewal — Approved', function () {
    $doc = submittedRenewal();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('reject terminates the renewal', function () {
    $doc = submittedRenewal();

    $this->engine->reject($doc, $this->sdaoA, 'Not approved.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
    expect($doc->current_step_position)->toBeNull();
});

test('return sends the renewal back for revision', function () {
    $doc = submittedRenewal();

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please fix the contact info.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
});

test('non-SDAO user cannot approve a renewal', function () {
    $doc = submittedRenewal();

    expect(fn () => $this->engine->approve($doc, $this->outsider))
        ->toThrow(UnauthorizedApproverException::class);
});

test('review show endpoint returns the renewal with detail and history', function () {
    $doc = submittedRenewal();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.renewals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/renewals/show')
            ->has('document')
            ->has('detail')
            ->has('history')
        );
});

// ── HTTP: quorum-completing approve must not 403 (regression) ────────────────

test('HTTP: first SDAO approve redirects back to the review show page', function () {
    $doc = submittedRenewal();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.renewals.approve', $doc))
        ->assertRedirect(route('review.renewals.show', $doc));
});

test('HTTP: quorum-completing SDAO approve redirects to the queue, not a 403', function () {
    $doc = submittedRenewal();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.renewals.approve', $doc));

    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->post(route('review.renewals.approve', $doc))
        ->assertRedirect(route('review.renewals.index'));

    // Following the redirect must succeed, not 403 — the actual regression.
    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->get(route('review.renewals.index'))
        ->assertOk();
});
