<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use App\Renewals\SubmitOrganizationRenewal;
use App\Renewals\UpdateOrganizationRenewal;
use App\Support\AcademicYear;
use Carbon\Carbon;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->registrationAction = app(SubmitOrganizationRegistration::class);
    $this->renewalAction = app(SubmitOrganizationRenewal::class);
    $this->updateRenewalAction = app(UpdateOrganizationRenewal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

afterEach(function () {
    // Some tests below travel through academic years — always restore real time.
    $this->travelBack();
});

/**
 * Submits and dual-approves an organization registration for the given org,
 * returning the now-Approved Document. Uses the real action + engine (not
 * raw factories) so the renewal's "prior approved record" precondition is
 * genuinely satisfied.
 */
function submitAndApproveRegistrationFor(User $actor, Organization $org, array $overrides = []): Document
{
    $p = array_merge([
        'organizationType' => OrganizationType::CoCurricular,
        'description' => 'Original description.',
        'contactPerson' => 'Original Person',
        'contactNumber' => '09171111111',
        'contactEmail' => 'original@example.test',
        'dateOrganized' => '2020-06-01',
        'roster' => ['Member One'],
    ], $overrides);

    $document = app(SubmitOrganizationRegistration::class)->execute(
        actor: $actor,
        organization: $org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    $engine = app(ApprovalEngine::class);
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $engine->approve($document, $sdaoA);
    $document->refresh();
    $engine->approve($document, $sdaoB);
    $document->refresh();

    return $document;
}

function renewalPayload(array $overrides = []): array
{
    return array_merge([
        'organizationType' => OrganizationType::CoCurricular,
        'description' => 'Renewed description.',
        'contactPerson' => 'Renewed Contact',
        'contactNumber' => '09172222222',
        'contactEmail' => 'renewed@example.test',
        'dateOrganized' => '2020-06-01',
        'roster' => ['Member One'],
    ], $overrides);
}

test('renewal requires a prior approved registration', function () {
    $p = renewalPayload();

    expect(fn () => $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    ))->toThrow(ValidationException::class);
});

test('unaffiliated user cannot submit a renewal even with a prior approved registration', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $outsider = User::factory()->create();
    $p = renewalPayload();

    expect(fn () => $this->renewalAction->execute(
        actor: $outsider,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    ))->toThrow(AuthorizationException::class);
});

test('affiliated officer can submit a renewal after an approved registration', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect($renewal->status)->toBe(DocumentStatus::InReview);
    expect($renewal->form_type)->toBe(FormType::OrganizationRenewal);
    expect($renewal->registrationDetail->academic_year)->toBe(AcademicYear::current());
});

test('a second renewal for the same org+year is blocked while the first is non-rejected', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $p = renewalPayload();

    $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect(fn () => $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: 'A second attempt.',
        contactPerson: 'Second Attempt',
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    ))->toThrow(ValidationException::class);
});

test('a rejected renewal frees the slot — a new renewal for the same year is allowed', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $p = renewalPayload();

    $firstRenewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    $this->engine->reject($firstRenewal, $this->sdaoA, 'Incomplete.');
    $firstRenewal->refresh();
    expect($firstRenewal->status)->toBe(DocumentStatus::Rejected);

    $secondRenewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: 'Second attempt after rejection.',
        contactPerson: 'Second Attempt',
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    expect($secondRenewal->status)->toBe(DocumentStatus::InReview);
    expect($secondRenewal->id)->not->toBe($firstRenewal->id);
});

test('the prior approved record is preserved — renewal creates a new row, never overwrites it', function () {
    $reg = submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    $reg->refresh()->load('registrationDetail');
    $renewal->load('registrationDetail');

    expect($reg->id)->not->toBe($renewal->id);
    expect($reg->registrationDetail->contact_person)->toBe('Original Person'); // untouched
    expect($renewal->registrationDetail->contact_person)->toBe('Renewed Contact');
});

// ── Addition 1: academic_year must be immutable across return/resubmit ──────

test('academic_year is unchanged after a renewal is returned for revision and resubmitted', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        description: $p['description'],
        contactPerson: $p['contactPerson'],
        contactNumber: $p['contactNumber'],
        contactEmail: $p['contactEmail'],
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    $originalAcademicYear = $renewal->registrationDetail->academic_year;
    expect($originalAcademicYear)->toBe(AcademicYear::current());

    $this->engine->returnForRevision($renewal, $this->sdaoA, 'Please fix contact info.');
    $renewal->refresh();
    expect($renewal->status)->toBe(DocumentStatus::Returned);

    $this->updateRenewalAction->execute(
        actor: $this->studentAlpha,
        document: $renewal,
        organizationType: $p['organizationType'],
        description: 'Revised description.',
        contactPerson: 'Revised Contact',
        contactNumber: '09179999999',
        contactEmail: 'revised@example.test',
        dateOrganized: $p['dateOrganized'],
        roster: $p['roster'],
    );

    $renewal->refresh();
    $renewal->load('registrationDetail');

    expect($renewal->status)->toBe(DocumentStatus::InReview);
    // The field that legitimately changes on revision:
    expect($renewal->registrationDetail->contact_person)->toBe('Revised Contact');
    // The field that must NEVER change across return/resubmit:
    expect($renewal->registrationDetail->academic_year)->toBe($originalAcademicYear);
});

// ── Addition 2: carry-forward must chain from the most recent approved record, not always the original ──

test('renewing an already-renewed org carries forward from the most recent renewal, not the original registration', function () {
    $this->travelTo(Carbon::parse('2024-09-01'));

    submitAndApproveRegistrationFor($this->studentAlpha, $this->org, [
        'contactPerson' => 'Original Registration Person',
    ]);

    $this->travelTo(Carbon::parse('2025-09-01'));

    $renewalAY1 = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: OrganizationType::CoCurricular,
        description: 'AY1 renewal description.',
        contactPerson: 'AY1 Renewal Person',
        contactNumber: '09171111111',
        contactEmail: 'ay1@example.test',
        dateOrganized: '2020-06-01',
        roster: ['Member One'],
    );
    $this->engine->approve($renewalAY1, $this->sdaoA);
    $renewalAY1->refresh();
    $this->engine->approve($renewalAY1, $this->sdaoB);
    $renewalAY1->refresh();
    expect($renewalAY1->status)->toBe(DocumentStatus::Approved);

    $this->travelTo(Carbon::parse('2026-09-01'));

    // Direct action-level check: the query must chain to the AY1 renewal, not the original registration.
    $mostRecent = $this->renewalAction->mostRecentApprovedRecord($this->org);
    expect($mostRecent->id)->toBe($renewalAY1->id);
    expect($mostRecent->registrationDetail->contact_person)->toBe('AY1 Renewal Person');

    // HTTP-level check: the AY2 create form must pre-fill from the AY1 renewal.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('renewals.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/create')
            ->where('priorRecord.contact_person', 'AY1 Renewal Person')
        );
});
