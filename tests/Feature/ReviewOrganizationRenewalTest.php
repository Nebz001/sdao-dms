<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
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

    $registration = app(SubmitOrganizationRegistration::class)->execute(
        actor: $student,
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

    return app(SubmitOrganizationRenewal::class)->execute(
        actor: $student,
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
