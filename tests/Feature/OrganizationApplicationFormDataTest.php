<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Printing\OrganizationApplicationForm;
use Carbon\Carbon;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Covers App\Printing\OrganizationApplicationForm::data() — the printable
 * form's derivation logic — directly, without rendering a PDF. dompdf output
 * is compressed binary and each render costs real time; the data array is
 * where every content decision actually lives, so that's what's tested here.
 * A single render-smoke test lives in DocumentPrintRenderTest instead.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->form = app(OrganizationApplicationForm::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/**
 * @param  array<string, mixed>  $detailOverrides
 */
function printableDocument(
    Organization $org,
    User $submitter,
    ?User $adviser,
    FormType $formType = FormType::OrganizationRegistration,
    array $detailOverrides = [],
): Document {
    $doc = Document::create([
        'form_type' => $formType,
        'variant' => null,
        'title' => "Test — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);

    OrganizationRegistrationDetail::create(array_merge([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'To advance the field.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => $adviser?->id,
    ], $detailOverrides));

    return $doc;
}

function dataFor(Document $document): array
{
    $form = app(OrganizationApplicationForm::class);
    $document->load($form->eagerLoads());

    return $form->data($document);
}

// ── §7 checklist ─────────────────────────────────────────────────────────

test('checklist ticks only slots with an uploaded attachment', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);

    foreach (['letter_of_intent', 'application_form', 'officers_list', 'dean_endorsement_letter', 'proposed_projects_budget'] as $slot) {
        DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => $slot]);
    }
    // by_laws deliberately left unattached.

    $data = dataFor($doc);
    $checked = collect($data['checklist']['new'])->pluck('checked', 'label');

    expect($checked['Letter of Intent'])->toBeTrue();
    expect($checked['Application Form'])->toBeTrue();
    expect($checked['Updated List of Officers/Founders'])->toBeTrue();
    expect($checked['Letter from the College Dean endorsing the Faculty Adviser (Co-Curricular organizations)'])->toBeTrue();
    expect($checked['List of Proposed Projects with Proposed Budget for the AY'])->toBeTrue();
    expect($checked['By Laws of the Organization'])->toBeFalse();
});

test('a registration ticks nothing in the renewal column, and vice versa', function () {
    $registration = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRegistration);
    DocumentAttachment::factory()->create(['document_id' => $registration->id, 'slot_key' => 'letter_of_intent']);

    $registrationData = dataFor($registration);
    expect(collect($registrationData['checklist']['renewal'])->every(fn ($row) => $row['checked'] === false))->toBeTrue();
    expect(collect($registrationData['checklist']['new'])->contains('checked', true))->toBeTrue();

    $renewalOrg = Organization::factory()->create();
    $renewal = printableDocument($renewalOrg, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRenewal);
    DocumentAttachment::factory()->create(['document_id' => $renewal->id, 'slot_key' => 'letter_of_intent']);

    $renewalData = dataFor($renewal);
    expect(collect($renewalData['checklist']['new'])->every(fn ($row) => $row['checked'] === false))->toBeTrue();
    expect(collect($renewalData['checklist']['renewal'])->contains('checked', true))->toBeTrue();
});

test('the renewal checklist reproduces the paper form\'s duplicated "List of Past Projects" row verbatim', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRenewal);

    $data = dataFor($doc);
    $renewalRows = $data['checklist']['renewal'];

    // 6 registration slots + 3 renewal-only slots, but "List of Past
    // Projects" is printed TWICE on the real physical form (ACO-SA-F-002) —
    // 10 rows over 9 distinct slots. Do not "fix" this to 9 rows; it would
    // no longer match the paper form.
    $pastProjectsRows = collect($renewalRows)->filter(fn ($row) => $row['label'] === 'List of Past Projects');
    expect($pastProjectsRows)->toHaveCount(2);
    expect($pastProjectsRows->pluck('checked')->unique())->toHaveCount(1); // both share one checked state

    $withoutOthers = collect($renewalRows)->reject(fn ($row) => $row['label'] === 'Others');
    expect($withoutOthers)->toHaveCount(10);
});

test('the new-application checklist has exactly 6 slot rows plus Others', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRegistration);

    $rows = dataFor($doc)['checklist']['new'];
    expect($rows)->toHaveCount(7);
    expect(collect($rows)->last()['label'])->toBe('Others');
});

test('"Others" never ticks, on either column, regardless of attachments', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRenewal);
    foreach (['letter_of_intent', 'application_form', 'by_laws', 'officers_list', 'dean_endorsement_letter', 'proposed_projects_budget', 'past_projects_list', 'financial_statement', 'evaluation_summary'] as $slot) {
        DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => $slot]);
    }

    $data = dataFor($doc);
    $othersNew = collect($data['checklist']['new'])->firstWhere('label', 'Others');
    $othersRenewal = collect($data['checklist']['renewal'])->where('label', 'Others')->first();

    expect($othersNew['checked'])->toBeFalse();
    expect($othersRenewal['checked'])->toBeFalse();
});

// ── §3/§4/§5 endorsements ────────────────────────────────────────────────

test('SDAO endorsement is blank before any approval, partial after one, and complete after both', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $data = dataFor($doc);
    expect($data['endorsements']['sdao']->names)->toBe([]);
    expect($data['endorsements']['sdao']->date)->toBeNull();
    expect($data['endorsements']['sdao']->time)->toBeNull();
    expect($data['approval']['is_approved'])->toBeFalse();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    $data = dataFor($doc);
    expect($data['endorsements']['sdao']->names)->toHaveCount(1);
    expect($data['endorsements']['sdao']->date)->not->toBeNull();
    expect($data['endorsements']['sdao']->time)->not->toBeNull();
    // Invariant #3: a split/partial decision is not an approved decision.
    expect($data['approval']['is_approved'])->toBeFalse();

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $data = dataFor($doc);
    expect($data['endorsements']['sdao']->names)->toHaveCount(2);
    expect($data['approval']['is_approved'])->toBeTrue();
    expect($data['approval']['sdao_date'])->toBe($data['endorsements']['sdao']->date);
});

test('the Faculty Adviser column prints the adviser\'s name with a blank date and time', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);

    $data = dataFor($doc);

    expect($data['endorsements']['faculty_adviser']->names)->toBe([$this->adviserOne->name]);
    expect($data['endorsements']['faculty_adviser']->date)->toBeNull();
    expect($data['endorsements']['faculty_adviser']->time)->toBeNull();
});

test('College Dean and CRSO always print fully blank, even on a fully approved document', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $data = dataFor($doc);

    expect($data['endorsements']['college_dean']->names)->toBe([]);
    expect($data['endorsements']['college_dean']->date)->toBeNull();
    expect($data['endorsements']['college_dean']->time)->toBeNull();
    expect($data['endorsements']['crso']->names)->toBe([]);
    expect($data['endorsements']['crso']->date)->toBeNull();
    expect($data['approval']['crso_date'])->toBeNull();
    expect($data['approval']['is_probation'])->toBeFalse();
});

test('SDAO approval timestamps render in Asia/Manila, not the UTC storage timezone', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    // 16:30 UTC is 00:30 the next day in Asia/Manila (UTC+8) — a date-boundary
    // instant makes a timezone bug impossible to miss by eye.
    Carbon::setTestNow(Carbon::parse('2026-08-03 16:30:00', 'UTC'));
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    Carbon::setTestNow();
    $doc->refresh();

    $data = dataFor($doc);

    expect($data['endorsements']['sdao']->date)->toBe('08/04/2026');
    expect($data['endorsements']['sdao']->time)->toBe('12:30 AM');
});

// ── Academic year & NEW/RENEWAL discriminator ───────────────────────────

test('academic year is read from the stored column on a renewal', function () {
    $doc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRenewal, [
        'academic_year' => '2025-2026',
    ]);

    expect(dataFor($doc)['academic_year'])->toBe('2025-2026');
});

test('academic year on a registration is derived from the document\'s created_at, not the current date', function () {
    $septemberDoc = printableDocument($this->org, $this->studentAlpha, $this->adviserOne);
    $septemberDoc->forceFill(['created_at' => '2026-09-01'])->save();
    expect(dataFor($septemberDoc->fresh())['academic_year'])->toBe('2026-2027');

    $marchOrg = Organization::factory()->create();
    $marchDoc = printableDocument($marchOrg, $this->studentAlpha, $this->adviserOne);
    $marchDoc->forceFill(['created_at' => '2026-03-01'])->save();
    expect(dataFor($marchDoc->fresh())['academic_year'])->toBe('2025-2026');
});

test('is_new and is_renewal flags follow the document\'s form_type', function () {
    $registration = printableDocument($this->org, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRegistration);
    expect(dataFor($registration))->toMatchArray(['is_new' => true, 'is_renewal' => false]);

    $renewalOrg = Organization::factory()->create();
    $renewal = printableDocument($renewalOrg, $this->studentAlpha, $this->adviserOne, FormType::OrganizationRenewal);
    expect(dataFor($renewal))->toMatchArray(['is_new' => false, 'is_renewal' => true]);
});
