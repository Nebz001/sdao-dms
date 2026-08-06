<?php

use App\Approval\ApprovalEngine;
use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Enums\Sdg;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Printing\ActivityProposalForm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Covers App\Printing\ActivityProposalForm::data() — field mapping,
 * SignatureBlock auto-fill vs blank, the SDAO dual-approval case, the SHS
 * collapse, and the RoleDirectory-unresolvable-role degradation path — same
 * style as OrganizationApplicationFormDataTest: direct data() assertions,
 * not full PDF renders (a single render smoke test lives elsewhere).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->shs = Organization::where('name', 'SHS Student Council')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentGamma = User::where('email', 'student-gamma@sdao.test')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();
    $this->adviserShs = User::where('email', 'adviser-shs@sdao.test')->firstOrFail();
    $this->principalShs = User::where('email', 'principal-shs@sdao.test')->firstOrFail();
});

/**
 * Builds a Draft ActivityProposal document directly (not through
 * StartProposalDraft/SubmitActivityProposal) — variant must be set explicitly
 * so ApprovalEngine::submit() resolves the correct template, mirroring what
 * SubmitActivityProposal itself does before calling the generic engine.
 *
 * @param  array<string, mixed>  $proposalOverrides
 */
function activityProposalPrintDocument(
    Organization $org,
    User $submitter,
    ProposalVariant $variant,
    array $proposalOverrides = [],
): Document {
    $doc = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => $variant->value,
        'title' => 'Print Test Proposal',
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);

    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Backing Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => '2025-2026',
        'term' => 'first_term',
    ]);
    $calendarActivity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Print Test Activity',
        'venue' => 'Gym',
        'activity_date' => '2026-10-01',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    ActivityProposal::create(array_merge([
        'document_id' => $doc->id,
        'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
        'calendar_activity_id' => $calendarActivity->id,
        'title' => 'Print Test Proposal',
        'activity_nature' => ActivityNature::CoCurricular->value,
        'activity_type' => ActivityType::Competition->value,
        'partner_organizations' => ['Partner A', 'Partner B'],
        'target_sdg' => Sdg::LifeOnLand->value,
        'objectives' => 'Objectives text',
        'narrative' => 'Narrative text',
        'criteria_mechanics' => 'Criteria text',
        'program_flow' => 'Program flow text',
        'source_of_funding' => 'Sponsors',
        'expenses' => 'Venue rental',
        'proposed_budget' => 5000,
        'budget_source' => 'Org funds',
        'form_step' => 2,
    ], $proposalOverrides));

    return $doc;
}

function dataForProposal(Document $document): array
{
    $form = app(ActivityProposalForm::class);
    $document->load($form->eagerLoads());

    return $form->data($document);
}

// ── Field mapping ────────────────────────────────────────────────────────

test('page 1 and page 2 fields map from the stored proposal and its calendar activity', function () {
    OrganizationMembership::create([
        'user_id' => $this->studentAlpha->id,
        'organization_id' => $this->org->id,
        'position' => OfficerPosition::President->value,
        'academic_year' => '2025-2026',
        'is_active' => true,
    ]);

    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar);
    $data = dataForProposal($doc);

    expect($data['rso_name'])->toBe('Computing Society');
    expect($data['title_of_activity'])->toBe('Print Test Proposal');
    expect($data['partner_organizations'])->toBe(['Partner A', 'Partner B']);
    expect($data['target_sdg'])->toBe('SDG 15 — Life on Land');
    expect($data['proposed_budget'])->toBe('5,000.00');
    expect($data['budget_source'])->toBe('Org funds');
    expect($data['date_of_activity'])->toBe('10/01/2026');
    expect($data['venue'])->toBe('Gym');
    expect($data['proposed_time'])->toBe('09:00 AM – 11:00 AM');
    expect($data['objectives'])->toBe('Objectives text');
    expect($data['narrative'])->toBe('Narrative text');
    expect($data['criteria_mechanics'])->toBe('Criteria text');
    expect($data['program_flow'])->toBe('Program flow text');
    expect($data['source_of_funding'])->toBe('Sponsors');
    expect($data['expenses'])->toBe('Venue rental');
    expect($data['has_resource_person_resume'])->toBeFalse();
    expect($data['is_shs'])->toBeFalse();
    expect($data['school_name'])->toBe('School of Computing and IT');
    expect($data['program_name'])->toBe('BS Computer Science');
    expect($data['prepared_by_president'])->toBe('Student Alpha');

    $competitionRow = collect($data['type_checklist'])->firstWhere('label', 'Competition');
    expect($competitionRow['checked'])->toBeTrue();
    $seminarRow = collect($data['type_checklist'])->firstWhere('label', 'Seminar/Workshop');
    expect($seminarRow['checked'])->toBeFalse();
});

// ── Itemized expenses (client request, post-Part-2) ─────────────────────

test('expense_items maps to formatted rows plus a formatted grand total, and legacy expenses stays available as a fallback value', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'expense_items' => [
            ['label' => 'Venue rental', 'amount' => '5000'],
            ['label' => 'Sound system rental', 'amount' => '8000.5'],
        ],
    ]);
    $data = dataForProposal($doc);

    expect($data['expense_items'])->toBe([
        ['label' => 'Venue rental', 'amount' => '5,000.00'],
        ['label' => 'Sound system rental', 'amount' => '8,000.50'],
    ]);
    expect($data['expense_items_total'])->toBe('13,000.50');
    // The legacy prose value is still exposed even when expense_items is
    // present — the Blade view is what decides which one to render.
    expect($data['expenses'])->toBe('Venue rental');
});

test('the grand total sums in integer centavos, not floats, so it never drifts on a repeating-decimal case', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'expense_items' => [
            ['label' => 'Item A', 'amount' => '0.10'],
            ['label' => 'Item B', 'amount' => '0.20'],
        ],
    ]);
    $data = dataForProposal($doc);

    // A naive float sum (0.1 + 0.2) produces 0.30000000000000004 in PHP —
    // the centavos-based accessor must not reproduce that artifact.
    expect($data['expense_items_total'])->toBe('0.30');
});

test('a proposal with no expense_items rows exposes a null total and only the legacy expenses prose', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'expense_items' => null,
    ]);
    $data = dataForProposal($doc);

    expect($data['expense_items'])->toBeNull();
    expect($data['expense_items_total'])->toBeNull();
    expect($data['expenses'])->toBe('Venue rental');
});

test('the rendered Blade prints an itemized table with a TOTAL row when expense_items is present', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'expense_items' => [['label' => 'Venue rental', 'amount' => '5000']],
    ]);
    $form = app(ActivityProposalForm::class);
    $doc->load($form->eagerLoads());

    $html = view($form->view(), $form->data($doc))->render();

    expect($html)->toContain('Venue rental')
        ->toContain('TOTAL')
        ->toContain('5,000.00');
});

test('the rendered Blade falls back to the legacy prose paragraph when expense_items is empty', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'expense_items' => null,
    ]);
    $form = app(ActivityProposalForm::class);
    $doc->load($form->eagerLoads());

    $html = view($form->view(), $form->data($doc))->render();

    expect($html)->toContain('Venue rental')->not->toContain('TOTAL');
});

test('the duplicated "Fundraising Activity" row never ticks, and the checklist has exactly 10 type rows', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'activity_type' => ActivityType::DonationDriveFundraising->value,
    ]);
    $data = dataForProposal($doc);

    expect($data['type_checklist'])->toHaveCount(10);
    $fundraisingRows = collect($data['type_checklist'])->filter(fn ($row) => $row['label'] === 'Fundraising Activity');
    expect($fundraisingRows)->toHaveCount(1);
    expect($fundraisingRows->first()['checked'])->toBeFalse();

    $donationRow = collect($data['type_checklist'])->firstWhere('label', 'Donation Drive / Fundraising Activity');
    expect($donationRow['checked'])->toBeTrue();
});

test('nature checklist maps 1:1 to all 4 ActivityNature cases with no duplicates', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, [
        'activity_nature' => ActivityNature::CommunityExtension->value,
    ]);
    $data = dataForProposal($doc);

    expect($data['nature_checklist'])->toHaveCount(4);
    $checked = collect($data['nature_checklist'])->filter(fn ($row) => $row['checked']);
    expect($checked)->toHaveCount(1);
    expect($checked->first()['label'])->toContain('Community Extension');
});

test('"VI. Responsible Person/s" has no backing field and prepared_by_president is blank without an active president', function () {
    // A freshly factory-made org has no seeded officer memberships at all
    // (unlike Computing Society, which MembershipSeeder already binds
    // Student Alpha to as President).
    $orphanOrg = Organization::factory()->create();
    $doc = activityProposalPrintDocument($orphanOrg, $this->studentAlpha, ProposalVariant::RegularOnCalendar);
    $data = dataForProposal($doc);

    expect($data['prepared_by_president'])->toBeNull();
    expect($data)->not->toHaveKey('responsible_persons');
});

// ── Signature auto-fill vs blank ─────────────────────────────────────────

test('every single-approver signature shows the current role-holder pre-approval, with date/time only after an actual approval', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $data = dataForProposal($doc);

    // Adviser is step 1, not yet approved — name shown (pre-approval
    // preview, printable-before-approval per DocumentPrintController), but
    // no date/time.
    $adviser = $data['narrative_signatures']['adviser'];
    expect($adviser->names)->toBe(['Adviser One']);
    expect($adviser->date)->toBeNull();
    expect($adviser->time)->toBeNull();

    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();
    $data = dataForProposal($doc);

    $adviser = $data['narrative_signatures']['adviser'];
    expect($adviser->names)->toBe(['Adviser One']);
    expect($adviser->date)->not->toBeNull();
    expect($adviser->time)->not->toBeNull();
});

test('SDAO signature comes only from stepApprovals — blank before, partial after one, complete after both', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    foreach ([$this->adviserOne, $this->chairCs, $this->deanCcit] as $approver) {
        $this->engine->approve($doc, $approver);
        $doc->refresh();
    }

    $data = dataForProposal($doc);
    expect($data['tail_signatures']['sdao']->names)->toBe([]);
    expect($data['tail_signatures']['sdao']->date)->toBeNull();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $data = dataForProposal($doc);
    expect($data['tail_signatures']['sdao']->names)->toHaveCount(1);

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    $data = dataForProposal($doc);
    expect($data['tail_signatures']['sdao']->names)->toHaveCount(2);
    expect($data['tail_signatures']['sdao']->date)->not->toBeNull();
});

test('a fully approved proposal fills every tail and narrative signature with a name and a date', function () {
    $doc = activityProposalPrintDocument($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    foreach ([$this->adviserOne, $this->chairCs, $this->deanCcit, $this->sdaoA, $this->sdaoB, $this->asstDir, $this->acadDir, $this->execDir] as $approver) {
        $this->engine->approve($doc, $approver);
        $doc->refresh();
    }

    expect($doc->status)->toBe(DocumentStatus::Approved);
    $data = dataForProposal($doc);

    foreach (['adviser', 'reviewed_by', 'noted_by'] as $key) {
        expect($data['narrative_signatures'][$key]->names)->not->toBe([]);
        expect($data['narrative_signatures'][$key]->date)->not->toBeNull();
    }
    foreach (['sdao', 'asst_director', 'academic_director', 'executive_director'] as $key) {
        expect($data['tail_signatures'][$key]->names)->not->toBe([]);
        expect($data['tail_signatures'][$key]->date)->not->toBeNull();
    }
});

// ── SHS collapse ─────────────────────────────────────────────────────────

test('an SHS proposal collapses Program Chair + Dean into one Principal "Reviewed by" block, with Noted by null', function () {
    $doc = activityProposalPrintDocument($this->shs, $this->studentGamma, ProposalVariant::ShsOnCalendar);
    $data = dataForProposal($doc);

    expect($data['is_shs'])->toBeTrue();
    expect($data['narrative_signatures']['noted_by'])->toBeNull();
    expect($data['narrative_signatures']['reviewed_by']->roleLabel)->toBe('Principal');

    // Pre-approval preview still resolves the current principal by name.
    expect($data['narrative_signatures']['reviewed_by']->names)->toBe(['Principal SHS']);
});

test('an SHS proposal, once its Reviewed-by step approves, records the Principal\'s name and date', function () {
    $doc = activityProposalPrintDocument($this->shs, $this->studentGamma, ProposalVariant::ShsOnCalendar);
    $this->engine->submit($doc, $this->studentGamma);
    $doc->refresh();

    $this->engine->approve($doc, $this->adviserShs);
    $doc->refresh();
    $this->engine->approve($doc, $this->principalShs);
    $doc->refresh();

    $data = dataForProposal($doc);
    expect($data['narrative_signatures']['reviewed_by']->names)->toBe(['Principal SHS']);
    expect($data['narrative_signatures']['reviewed_by']->date)->not->toBeNull();
});

// ── RoleDirectory-unresolvable-role degradation ──────────────────────────

test('an org with no bound adviser degrades the Adviser signature to blank, not a fatal error', function () {
    $orphanOrg = Organization::factory()->create();

    $doc = activityProposalPrintDocument($orphanOrg, $this->studentAlpha, ProposalVariant::RegularOnCalendar);

    // Not submitted — no stepApprovals exist, and RoleDirectory::adviserFor()
    // throws ModelNotFoundException for an org with no bound adviser at all.
    // data() must degrade to a blank block, never throw.
    $data = dataForProposal($doc);

    expect($data['narrative_signatures']['adviser']->names)->toBe([]);
    expect($data['narrative_signatures']['adviser']->date)->toBeNull();
});

test('the print route responds successfully for a document whose adviser cannot be resolved, rather than 500ing', function () {
    $orphanOrg = Organization::factory()->create();
    $doc = activityProposalPrintDocument($orphanOrg, $this->studentAlpha, ProposalVariant::RegularOnCalendar);

    $this->actingAs($this->studentAlpha)
        ->get(route('documents.print', $doc))
        ->assertOk();
});
