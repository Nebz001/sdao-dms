<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\RenewalEligibility;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationStatusResolver;
use App\Renewals\SubmitOrganizationRenewal;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * App\Organizations\OrganizationStatusResolver — the single source of truth
 * for CLAUDE.md's derived org statuses (Active/NeedsRenewal/PendingReview/
 * Inactive) and the orthogonal `renewalDue` flag. Covers the status
 * predicate itself plus the three decisions recorded in the Stage 2 plan:
 * (1) Active needs only ONE active officer, (2) a Rejected renewal is never
 * in flight, (3) coversAcademicYearOrLater() and
 * hasNonRejectedRenewalForExactYear() are deliberately different checks.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->resolver = app(OrganizationStatusResolver::class);
    $this->renewalAction = app(SubmitOrganizationRenewal::class);
    $this->engine = app(ApprovalEngine::class);

    // Computing Society: President (studentAlpha) AND Secretary (studentDelta),
    // adviser bound (adviser-one). IT Guild: President (studentBeta) ONLY, no
    // secretary — the exact shape decision 1 is about. Both come straight
    // from MembershipSeeder/IdentitySeeder, no hand-built fixtures needed.
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->chessClub = Organization::where('name', 'University Chess Club')->firstOrFail(); // Extra-Curricular, no college
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentEpsilon = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    // Same fixed, deterministic starting period as SubmitOrganizationRenewalTest.
    resolverSetPeriod('2030-2031', Term::FirstTerm);
});

function resolverSetPeriod(string $academicYear, Term $term): void
{
    CurrentPeriod::set(new AcademicPeriod($academicYear, $term));
}

function resolverOpenRenewalSeason(): void
{
    resolverSetPeriod(CurrentPeriod::get()->academicYear, Term::ThirdTerm);
}

/**
 * Submits and dual-approves a registration for the given org, then stamps
 * its coverage — defaulting to the real approve-time rule (current
 * academic year, or current+grace during renewal season) unless
 * $coversAcademicYear overrides it, which is how tests simulate a lapsed
 * or already-graced coverage year without waiting real terms out. Mirrors
 * SubmitOrganizationRenewalTest's submitAndApproveRegistrationFor(), kept
 * as a separate, uniquely-named copy since Pest requires globally-unique
 * top-level function names across all Feature test files.
 */
function resolverApproveRegistrationFor(User $actor, Organization $org, ?string $coversAcademicYear = null): Document
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
        'purpose_of_organization' => 'Test organization.',
        'contact_person' => 'Test Contact',
        'contact_no' => '09171111111',
        'email_address' => 'test@example.test',
        'date_organized' => '2020-06-01',
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

    $period = CurrentPeriod::get();
    $document->registrationDetail->update([
        'academic_year' => $period->academicYear,
        'term' => $period->term->value,
        'covers_academic_year' => $coversAcademicYear
            ?? ($period->isRenewalSeason() ? $period->nextAcademicYear() : $period->academicYear),
    ]);

    return $document;
}

test('a covered org with an active president AND secretary resolves Active, all officer requirements met', function () {
    resolverApproveRegistrationFor($this->studentAlpha, $this->computingSociety);

    $result = $this->resolver->for($this->computingSociety);

    expect($result->status)->toBe(OrganizationStatus::Active);
    expect($result->requirements->hasPresident)->toBeTrue();
    expect($result->requirements->hasSecretary)->toBeTrue();
    expect($result->requirements->registrationApproved)->toBeTrue();
    expect($result->requirements->adviserBound)->toBeTrue();
    expect($result->requirements->isComplete())->toBeTrue();
});

test('a covered org with NO active officers resolves Inactive', function () {
    resolverApproveRegistrationFor($this->studentAlpha, $this->computingSociety);
    OrganizationMembership::where('organization_id', $this->computingSociety->id)->update(['is_active' => false]);

    $result = $this->resolver->for($this->computingSociety);

    expect($result->status)->toBe(OrganizationStatus::Inactive);
});

test('an org that was never approved and has nothing in flight resolves Inactive', function () {
    $result = $this->resolver->for($this->itGuild);

    expect($result->status)->toBe(OrganizationStatus::Inactive);
    expect($result->requirements->registrationApproved)->toBeFalse();
});

test('an org whose coverage has lapsed, with nothing currently in flight, resolves NeedsRenewal', function () {
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild, coversAcademicYear: '2029-2030');

    $result = $this->resolver->for($this->itGuild);

    expect($result->status)->toBe(OrganizationStatus::NeedsRenewal);
});

test('PendingReview outranks NeedsRenewal — a lapsed org with a renewal already in flight is PendingReview', function () {
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild, coversAcademicYear: '2029-2030');
    resolverOpenRenewalSeason();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentBeta,
        organization: $this->itGuild,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );
    expect($renewal->status)->toBe(DocumentStatus::InReview);

    $result = $this->resolver->for($this->itGuild);

    expect($result->status)->toBe(OrganizationStatus::PendingReview);
});

test('coverage is a >= match — an org already graced through next year still reads Active this year', function () {
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild, coversAcademicYear: '2031-2032');

    $result = $this->resolver->for($this->itGuild, new AcademicPeriod('2030-2031', Term::FirstTerm));

    expect($result->status)->toBe(OrganizationStatus::Active);
    expect($result->requirements->coverageCurrent)->toBeTrue();
});

/**
 * Decision 3, pinned: coversAcademicYearOrLater() and
 * hasNonRejectedRenewalForExactYear() answer different questions and must
 * never be merged. This is the case AdminDashboardController::orgCompliance()
 * got wrong before this stage: an org covered ONLY via an approved
 * REGISTRATION (never a renewal) must still read as covered by the
 * resolver's check, while the renewal-only uniqueness guard correctly sees
 * no renewal at all.
 */
test('coverage sourced from an approved registration is recognized by coversAcademicYearOrLater, unlike the renewal-only exact-year guard', function () {
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild, coversAcademicYear: '2030-2031');

    expect($this->resolver->coversAcademicYearOrLater($this->itGuild, '2030-2031'))->toBeTrue();
    expect($this->renewalAction->hasNonRejectedRenewalForExactYear($this->itGuild, '2030-2031'))->toBeFalse();

    $result = $this->resolver->for($this->itGuild);
    expect($result->status)->toBe(OrganizationStatus::Active);
});

test('renewalDue always agrees with SubmitOrganizationRenewal::eligibilityFor()->isEligible()', function () {
    // No prior record at all.
    expect($this->resolver->for($this->itGuild)->renewalDue)
        ->toBe($this->renewalAction->eligibilityFor($this->itGuild)->isEligible())
        ->toBeFalse();

    // Approved, but season closed (still 1st term).
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild);
    expect($this->resolver->for($this->itGuild)->renewalDue)
        ->toBe($this->renewalAction->eligibilityFor($this->itGuild)->isEligible())
        ->toBeFalse();

    // Season opens — now genuinely eligible.
    resolverOpenRenewalSeason();
    expect($this->resolver->for($this->itGuild)->renewalDue)
        ->toBe($this->renewalAction->eligibilityFor($this->itGuild)->isEligible())
        ->toBeTrue();

    // Already filed this year — no longer due.
    $this->renewalAction->execute(
        actor: $this->studentBeta,
        organization: $this->itGuild,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );
    expect($this->resolver->for($this->itGuild)->renewalDue)
        ->toBe($this->renewalAction->eligibilityFor($this->itGuild)->isEligible())
        ->toBeFalse();
    expect($this->resolver->for($this->itGuild)->eligibility->status)->toBe(RenewalEligibility::AlreadyFiledThisYear);
});

/**
 * Decision 1: Active needs only ONE active officer — a president alone is
 * sufficient, and so is a secretary alone. The missing role is an unmet
 * checklist item, never a status downgrade.
 */
test('a president alone is sufficient for Active', function () {
    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild);

    $result = $this->resolver->for($this->itGuild);

    expect($result->status)->toBe(OrganizationStatus::Active);
    expect($result->requirements->hasPresident)->toBeTrue();
    expect($result->requirements->hasSecretary)->toBeFalse();
    expect($result->requirements->isComplete())->toBeFalse();
});

test('a secretary alone (no active president) is also sufficient for Active', function () {
    resolverApproveRegistrationFor($this->studentAlpha, $this->computingSociety);
    OrganizationMembership::where('organization_id', $this->computingSociety->id)
        ->where('position', 'president')
        ->update(['is_active' => false]);

    $result = $this->resolver->for($this->computingSociety);

    expect($result->status)->toBe(OrganizationStatus::Active);
    expect($result->requirements->hasPresident)->toBeFalse();
    expect($result->requirements->hasSecretary)->toBeTrue();
});

/**
 * Decision 2: a Rejected renewal does not count as in flight, and does not
 * block a fresh renewal for the same covered year. Complements
 * SubmitOrganizationRenewalTest's "a rejected renewal frees the slot" test
 * with the resolver-level assertion that a rejected renewal doesn't drag
 * the org's status down to PendingReview either.
 */
test('a rejected renewal does not read as in flight and does not block a fresh renewal for the same year', function () {
    expect(DocumentStatus::Rejected->isInFlight())->toBeFalse();

    resolverApproveRegistrationFor($this->studentBeta, $this->itGuild);
    resolverOpenRenewalSeason();

    $renewal = $this->renewalAction->execute(
        actor: $this->studentBeta,
        organization: $this->itGuild,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );

    $this->engine->reject($renewal, $this->sdaoA, 'Incomplete.');
    $renewal->refresh();
    expect($renewal->status)->toBe(DocumentStatus::Rejected);

    // Rejected renewal must not read as PendingReview — the org's
    // registration coverage still stands.
    $result = $this->resolver->for($this->itGuild);
    expect($result->status)->not->toBe(OrganizationStatus::PendingReview);
    expect($result->status)->toBe(OrganizationStatus::Active);

    // And a fresh renewal for the SAME covered year is not blocked.
    $secondRenewal = $this->renewalAction->execute(
        actor: $this->studentBeta,
        organization: $this->itGuild,
        purposeOfOrganization: 'Second attempt after rejection.',
        contactPerson: 'Second Attempt',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );
    expect($secondRenewal->status)->toBe(DocumentStatus::InReview);
    expect($secondRenewal->id)->not->toBe($renewal->id);

    // The org's registration coverage (this academic year) never lapsed in
    // this scenario, so it correctly stays Active even with a renewal now
    // genuinely in flight — `covered` short-circuits before `inFlight` is
    // even considered. See the separate "PendingReview outranks
    // NeedsRenewal" test above for the lapsed-and-in-flight case.
    $result = $this->resolver->for($this->itGuild);
    expect($result->status)->toBe(OrganizationStatus::Active);
});

test('an Extra-Curricular organization with no school resolves identically to a regular-school org', function () {
    expect($this->chessClub->hasNoSchool())->toBeTrue();

    resolverApproveRegistrationFor($this->studentEpsilon, $this->chessClub);

    $result = $this->resolver->for($this->chessClub);

    expect($result->status)->toBe(OrganizationStatus::Active);
    expect($result->requirements->adviserBound)->toBeTrue();
    expect($result->requirements->hasPresident)->toBeTrue();
});
