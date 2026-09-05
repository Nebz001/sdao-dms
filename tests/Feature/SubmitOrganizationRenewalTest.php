<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\RenewalEligibility;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use App\Renewals\UpdateOrganizationRenewal;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->renewalAction = app(SubmitOrganizationRenewal::class);
    $this->updateRenewalAction = app(UpdateOrganizationRenewal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    // A fixed, deterministic starting period — most tests approve a
    // registration in 1st term, then advance to 3rd term of the SAME
    // academic year to open renewal season, so behavior never depends on the
    // real wall-clock date the suite happens to run on.
    setPeriod('2030-2031', Term::FirstTerm);
});

function setPeriod(string $academicYear, Term $term): void
{
    CurrentPeriod::set(new AcademicPeriod($academicYear, $term));
}

/**
 * Advances the CURRENT academic year's term to 3rd — opens renewal season
 * without moving into a new academic year.
 */
function openRenewalSeason(): void
{
    setPeriod(CurrentPeriod::get()->academicYear, Term::ThirdTerm);
}

/**
 * Submits and dual-approves an organization registration for the given org,
 * returning the now-Approved Document. Builds the Document/detail rows
 * directly and drives them through the real ApprovalEngine, so the renewal's
 * "prior approved record" precondition is genuinely satisfied.
 *
 * Bypasses App\Registrations\ApproveOrganizationRegistration (this fixture
 * predates the founding-flow's adviser binding and doesn't need it), so it
 * must replicate that action's approve-time period/coverage stamp itself —
 * see ApproveOrganizationRegistrationTest for coverage that the REAL action
 * does this.
 */
function submitAndApproveRegistrationFor(User $actor, Organization $org, array $overrides = []): Document
{
    $p = array_merge([
        'organizationType' => OrganizationType::CoCurricular,
        'purposeOfOrganization' => 'Original description.',
        'contactPerson' => 'Original Person',
        'contactNo' => '09171111111',
        'emailAddress' => 'original@example.test',
        'dateOrganized' => '2020-06-01',
    ], $overrides);

    // Built directly (not via SubmitOrganizationRegistration): renewal tests
    // only need a valid PRIOR APPROVED registration fixture for an org the
    // actor is already bound to (via MembershipSeeder) — they don't exercise
    // registration-submission mechanics, which now (Phase 2 item 5) require a
    // not-yet-affiliated founding student, the opposite of this fixture's
    // shape. Registration submission itself is covered by
    // SubmitRegistrationTest / OrganizationFoundingTest.
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
        'organization_type' => $p['organizationType']->value,
        'purpose_of_organization' => $p['purposeOfOrganization'],
        'contact_person' => $p['contactPerson'],
        'contact_no' => $p['contactNo'],
        'email_address' => $p['emailAddress'],
        'date_organized' => $p['dateOrganized'],
        'adviser_id' => null,
    ]);

    $engine = app(ApprovalEngine::class);
    $engine->submit($document, $actor);
    $document->refresh();

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $engine->approve($document, $sdaoA);
    $document->refresh();
    $engine->approve($document, $sdaoB);
    $document->refresh();

    // Replicates ApproveOrganizationRegistration's approve-time stamp, since
    // this fixture bypasses that action entirely.
    $period = CurrentPeriod::get();
    $document->registrationDetail->update([
        'academic_year' => $period->academicYear,
        'term' => $period->term->value,
        'covers_academic_year' => $period->isRenewalSeason() ? $period->nextAcademicYear() : $period->academicYear,
    ]);

    return $document;
}

function renewalPayload(array $overrides = []): array
{
    return array_merge([
        'organizationType' => OrganizationType::CoCurricular,
        'purposeOfOrganization' => 'Renewed description.',
        'contactPerson' => 'Renewed Contact',
        'contactNo' => '09172222222',
        'emailAddress' => 'renewed@example.test',
        'dateOrganized' => '2020-06-01',
    ], $overrides);
}

test('renewal requires a prior approved registration', function () {
    openRenewalSeason();
    $p = renewalPayload();

    expect(fn () => $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    ))->toThrow(ValidationException::class);
});

test('unaffiliated user cannot submit a renewal even with a prior approved registration', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $outsider = User::factory()->create();
    $p = renewalPayload();

    expect(fn () => $this->renewalAction->execute(
        actor: $outsider,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    ))->toThrow(AuthorizationException::class);
});

test('affiliated officer can submit a renewal once renewal season opens', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    expect($renewal->status)->toBe(DocumentStatus::InReview);
    expect($renewal->form_type)->toBe(FormType::OrganizationRenewal);
    expect($renewal->registrationDetail->academic_year)->toBe('2030-2031');
    expect($renewal->registrationDetail->term)->toBe(Term::ThirdTerm);
    // A renewal filed during 3rd term of an academic year covers the NEXT one.
    expect($renewal->registrationDetail->covers_academic_year)->toBe('2031-2032');
});

test('a second renewal for the same covered year is blocked while the first is non-rejected', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    expect(fn () => $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: 'A second attempt.',
        contactPerson: 'Second Attempt',
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    ))->toThrow(ValidationException::class);
});

test('a rejected renewal frees the slot — a new renewal for the same covered year is allowed', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $firstRenewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    $this->engine->reject($firstRenewal, $this->sdaoA, 'Incomplete.');
    $firstRenewal->refresh();
    expect($firstRenewal->status)->toBe(DocumentStatus::Rejected);

    $secondRenewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: 'Second attempt after rejection.',
        contactPerson: 'Second Attempt',
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    expect($secondRenewal->status)->toBe(DocumentStatus::InReview);
    expect($secondRenewal->id)->not->toBe($firstRenewal->id);
});

test('the prior approved record is preserved — renewal creates a new row, never overwrites it', function () {
    $reg = submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    $reg->refresh()->load('registrationDetail');
    $renewal->load('registrationDetail');

    expect($reg->id)->not->toBe($renewal->id);
    expect($reg->registrationDetail->contact_person)->toBe('Original Person'); // untouched
    expect($renewal->registrationDetail->contact_person)->toBe('Renewed Contact');
});

// ── Addition 1: academic_year/term/coverage must be immutable across return/resubmit ──

test('renewal coverage is unchanged after a renewal is returned for revision and resubmitted', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    $originalCoverage = $renewal->registrationDetail->covers_academic_year;
    expect($originalCoverage)->toBe('2031-2032');

    $this->engine->returnForRevision($renewal, $this->sdaoA, 'Please fix contact info.');
    $renewal->refresh();
    expect($renewal->status)->toBe(DocumentStatus::Returned);

    $this->updateRenewalAction->execute(
        actor: $this->studentAlpha,
        document: $renewal,
        organizationType: $p['organizationType'],
        purposeOfOrganization: 'Revised description.',
        contactPerson: 'Revised Contact',
        contactNo: '09179999999',
        emailAddress: 'revised@example.test',
        dateOrganized: $p['dateOrganized'],
    );

    $renewal->refresh();
    $renewal->load('registrationDetail');

    expect($renewal->status)->toBe(DocumentStatus::InReview);
    // The field that legitimately changes on revision:
    expect($renewal->registrationDetail->contact_person)->toBe('Revised Contact');
    // The fields that must NEVER change across return/resubmit:
    expect($renewal->registrationDetail->covers_academic_year)->toBe($originalCoverage);
});

// ── Addition 2: carry-forward must chain from the most recent approved record, not always the original ──

test('renewing an already-renewed org carries forward from the most recent renewal, not the original registration', function () {
    setPeriod('2024-2025', Term::FirstTerm);

    submitAndApproveRegistrationFor($this->studentAlpha, $this->org, [
        'contactPerson' => 'Original Registration Person',
    ]);

    openRenewalSeason(); // 2024-2025, 3rd term -> covers 2025-2026

    $renewalAY1 = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'AY1 renewal description.',
        contactPerson: 'AY1 Renewal Person',
        contactNo: '09171111111',
        emailAddress: 'ay1@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );
    $this->engine->approve($renewalAY1, $this->sdaoA);
    $renewalAY1->refresh();
    $this->engine->approve($renewalAY1, $this->sdaoB);
    $renewalAY1->refresh();
    expect($renewalAY1->status)->toBe(DocumentStatus::Approved);

    // The next academic year's renewal season opens.
    setPeriod('2025-2026', Term::ThirdTerm);

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

// ── Renewal season: 3rd-term gate, grace, and coverage rollover ──────────

test('renewal is refused while the term is 1st or 2nd — season closed', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);

    // Still 1st term (from beforeEach) — season is closed.
    $eligibility = $this->renewalAction->eligibilityFor($this->org);
    expect($eligibility->status)->toBe(RenewalEligibility::SeasonClosed);
    expect($eligibility->message())->toContain('opens during 3rd Term');

    setPeriod('2030-2031', Term::SecondTerm);
    expect($this->renewalAction->eligibilityFor($this->org)->status)->toBe(RenewalEligibility::SeasonClosed);
});

test('an organization approved DURING 3rd term is not yet due — grace', function () {
    setPeriod('2030-2031', Term::ThirdTerm);
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);

    // Still the same season the org was just founded in.
    $eligibility = $this->renewalAction->eligibilityFor($this->org);
    expect($eligibility->status)->toBe(RenewalEligibility::NotYetDue);
    expect($eligibility->message())->toContain('already covered for 2031-2032');

    expect(fn () => $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'x',
        contactPerson: 'x',
        contactNo: '09170000000',
        emailAddress: 'x@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    ))->toThrow(ValidationException::class);
});

test('an organization approved in 1st term of the same year can renew once season opens', function () {
    // beforeEach leaves the period at (2030-2031, 1st term).
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();

    expect($this->renewalAction->eligibilityFor($this->org)->status)->toBe(RenewalEligibility::Eligible);
});

test('filing a second renewal in the same season reports AlreadyFiledThisYear with the covered year named', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );

    $eligibility = $this->renewalAction->eligibilityFor($this->org);
    expect($eligibility->status)->toBe(RenewalEligibility::AlreadyFiledThisYear);
    expect($eligibility->message())->toBe('A renewal covering 2031-2032 has already been filed for this organization.');
});

test('after renewing, advancing to next years 1st term closes the season and the org stays covered', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    openRenewalSeason();
    $p = renewalPayload();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        organizationType: $p['organizationType'],
        purposeOfOrganization: $p['purposeOfOrganization'],
        contactPerson: $p['contactPerson'],
        contactNo: $p['contactNo'],
        emailAddress: $p['emailAddress'],
        dateOrganized: $p['dateOrganized'],
        attachmentFiles: renewalAttachmentFiles(),
    );
    $this->engine->approve($renewal, $this->sdaoA);
    $renewal->refresh();
    $this->engine->approve($renewal, $this->sdaoB);
    $renewal->refresh();
    expect($renewal->status)->toBe(DocumentStatus::Approved);

    setPeriod('2031-2032', Term::FirstTerm);

    $eligibility = $this->renewalAction->eligibilityFor($this->org);
    expect($eligibility->status)->toBe(RenewalEligibility::SeasonClosed);
    expect($eligibility->coversThroughAcademicYear)->toBe('2031-2032');
});

test('renewals/create renders a season-closed empty state instead of the form', function () {
    submitAndApproveRegistrationFor($this->studentAlpha, $this->org);
    // Still 1st term — season closed.

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('renewals.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/create')
            ->where('eligibility.status', 'season_closed')
            ->where('eligibility.message', fn ($message) => str_contains($message, 'opens during 3rd Term'))
        );
});
