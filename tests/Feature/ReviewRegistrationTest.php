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
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

/** Create a submitted (InReview) registration for Computing Society. */
function submittedRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
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

    return $doc;
}

test('first SDAO approval does not yet approve the document (quorum not reached)', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
});

test('second SDAO approval completes the registration', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('SDAO can reject a registration permanently', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->reject($doc, $this->sdaoA, 'Incomplete documentation.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
});

test('SDAO can return a registration for revision', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please attach the constitution.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->current_step_position)->toBe(1);
});

test('split decision (one approve, one return) puts document in Returned', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->sdaoB, 'Need more info.');
    $doc->refresh();

    // The return clears partials; document is Returned.
    expect($doc->status)->toBe(DocumentStatus::Returned);
});

test('non-SDAO user cannot review a registration', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);
    $adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();

    expect(fn () => $this->engine->approve($doc, $adviser))
        ->toThrow(UnauthorizedApproverException::class);
});

// ── HTTP: quorum-completing approve must not 403 (regression) ────────────────

test('HTTP: first SDAO approve redirects back to the review show page', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc))
        ->assertRedirect(route('review.registrations.show', $doc));
});

test('HTTP: quorum-completing SDAO approve redirects to the queue, not a 403', function () {
    $doc = submittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc));

    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->post(route('review.registrations.approve', $doc))
        ->assertRedirect(route('review.registrations.index'));

    // Following the redirect must succeed, not 403 — the actual regression.
    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk();
});
