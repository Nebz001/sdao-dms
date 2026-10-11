<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\FixDraft;
use App\Approval\ReturnedEditLock;
use App\Attachments\AttachmentStorage;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Fix and Resend: while a document is Returned only what the approver flagged
 * can change, and every flagged item has to change before it can be resent.
 * These tests drive the real routes (the edit page, the resubmit PUT, Save
 * draft, the standalone upload endpoint) because the rule is enforced on the
 * server, not by what the form happens to show.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->otherOrgStudent = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * A returned Registration or Renewal with every required file on file.
 *
 * @param  array<int, string>  $flagged
 * @param  array<string, string>  $notes
 */
function fixReturnedDocument(FormType $type, array $flagged, array $notes = []): Document
{
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    $document = Document::factory()->create([
        'form_type' => $type,
        'organization_id' => Organization::where('name', 'Computing Society')->firstOrFail()->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);

    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $document->id,
        'organization_type' => OrganizationType::CoCurricular,
        'purpose_of_organization' => 'Original purpose.',
        'contact_person' => 'Old Person',
        'contact_no' => '09170000000',
        'email_address' => 'old@nu-lipa.edu.ph',
        'date_organized' => '2020-06-01',
    ]);

    app(AttachmentStorage::class)->storeMany(
        $document,
        $type === FormType::OrganizationRenewal ? renewalAttachmentFiles() : registrationAttachmentFiles(),
        $student,
    );

    app(ApprovalEngine::class)->submit($document, $student);
    app(ApprovalEngine::class)->returnForRevision($document->refresh(), $sdao, 'Please revise.', $flagged, $notes);

    return $document->refresh();
}

/** The fields a form posts, for the form types that share the registration detail. */
function fixDetailPayload(array $overrides = []): array
{
    return [
        'purpose_of_organization' => 'Original purpose.',
        'contact_person' => 'Old Person',
        'contact_no' => '09170000000',
        'email_address' => 'old@nu-lipa.edu.ph',
        'date_organized' => '2020-06-01',
        ...$overrides,
    ];
}

function fixUpdateRoute(Document $document): string
{
    return route($document->form_type === FormType::OrganizationRenewal ? 'renewals.update' : 'registrations.update', $document);
}

function fixEditRoute(Document $document): string
{
    return route($document->form_type->studentEditRouteName(), $document);
}

dataset('detail forms', [
    'registration' => FormType::OrganizationRegistration,
    'renewal' => FormType::OrganizationRenewal,
]);

// ── The server refuses a change to anything that was not flagged ────────────

test('a changed locked field is refused with a clear message, and nothing is saved', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information']);

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), fixDetailPayload(['contact_person' => 'New Person', 'purpose_of_organization' => 'Sneaked in.']))
        ->assertSessionHasErrors(['locked' => 'These were not flagged, so they can’t be changed: Purpose of Organization.']);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);
    expect($document->registrationDetail->purpose_of_organization)->toBe('Original purpose.');
    // The refusal rolled the whole write back, including the flagged field.
    expect($document->registrationDetail->contact_person)->toBe('Old Person');
    expect($document->transitions()->where('action', TransitionAction::Resubmitted->value)->exists())->toBeFalse();
})->with('detail forms');

test('a file for a slot that was not flagged is refused, and the stored file is untouched', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information']);
    $before = $document->attachments()->where('slot_key', 'by_laws')->firstOrFail();

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), [
            ...fixDetailPayload(['contact_person' => 'New Person']),
            'attachments' => ['by_laws' => UploadedFile::fake()->create('sneaky.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('locked');

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
    expect(DocumentAttachment::find($before->id))->not->toBeNull();
    expect($document->attachments()->where('slot_key', 'by_laws')->count())->toBe(1);
})->with('detail forms');

// ── ... and refuses a resend while a flagged item is unchanged ──────────────

test('a flagged section that was not changed blocks the resend, naming what is missing', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information', 'organization_details']);

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), fixDetailPayload(['contact_person' => 'New Person']))
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Organization Details.']);

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
})->with('detail forms');

test('trimming whitespace is not a change', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information']);

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), fixDetailPayload(['contact_person' => '  Old Person  ']))
        ->assertSessionHasErrors('flagged');

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
})->with('detail forms');

test('a flagged file needs a real new upload, and is accepted once it has one', function (FormType $type) {
    $document = fixReturnedDocument($type, ['by_laws']);
    $old = $document->attachments()->where('slot_key', 'by_laws')->firstOrFail();

    // Nothing uploaded: refused, even though a file is already on record.
    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), fixDetailPayload())
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: By-Laws (needs a new file).']);
    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), [
            ...fixDetailPayload(),
            'attachments' => ['by_laws' => UploadedFile::fake()->create('new-by-laws.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect(DocumentAttachment::find($old->id))->toBeNull();
    expect($document->attachments()->where('slot_key', 'by_laws')->value('original_filename'))->toBe('new-by-laws.pdf');
    expect($document->transitions()->where('action', TransitionAction::Resubmitted->value)->count())->toBe(1);
})->with('detail forms');

test('every flagged item fixed: the resend goes through, keeping the locked values exactly', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information', 'organization_details', 'by_laws']);

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), [
            ...fixDetailPayload(['contact_person' => 'New Person', 'purpose_of_organization' => 'A better purpose.']),
            'attachments' => ['by_laws' => UploadedFile::fake()->create('new-by-laws.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->registrationDetail->contact_person)->toBe('New Person');
    expect($document->registrationDetail->email_address)->toBe('old@nu-lipa.edu.ph');
})->with('detail forms');

test('the Fix and Resend page posts only the flagged fields, and the locked ones are kept', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information']);

    // Exactly what the page sends: no purpose, no date, no email.
    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), ['contact_person' => 'New Person', 'contact_no' => '09179999999', 'email_address' => 'new@nu-lipa.edu.ph'])
        ->assertSessionHasNoErrors();

    $detail = $document->refresh()->registrationDetail;
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($detail->contact_person)->toBe('New Person');
    expect($detail->purpose_of_organization)->toBe('Original purpose.');
    expect($detail->date_organized->toDateString())->toBe('2020-06-01');
})->with('detail forms');

// ── A return with nothing fixable keeps today's rules ───────────────────────

test('a return with no flags keeps the whole form editable', function (FormType $type, array $flagged) {
    $document = fixReturnedDocument($type, $flagged);

    expect(ReturnedEditLock::for($document))->toBeNull();

    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), [
            ...fixDetailPayload(['contact_person' => 'New Person', 'purpose_of_organization' => 'Everything changed.']),
            'attachments' => ['by_laws' => UploadedFile::fake()->create('new-by-laws.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->status)->toBe(DocumentStatus::InReview);
})->with([
    'registration, no flags' => [FormType::OrganizationRegistration, []],
    'registration, only General' => [FormType::OrganizationRegistration, ['general']],
    'renewal, no flags' => [FormType::OrganizationRenewal, []],
    'renewal, only General' => [FormType::OrganizationRenewal, ['general']],
]);

test('the edit page tells the page what is flagged, by whom, and where the resend goes', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information', 'by_laws', 'general'], ['by_laws' => 'Wrong version.']);

    $this->actingAs($this->student)->withoutVite()
        ->get(fixEditRoute($document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fix.active', true)
            ->where('fix.role', 'SDAO')
            ->where('fix.resendLabel', 'Resend to SDAO')
            ->where('fix.items.0.key', 'contact_information')
            ->where('fix.items.1.key', 'by_laws')
            ->where('fix.items.1.comment', 'Wrong version.')
            ->where('fix.items.1.kind', 'file')
            ->where('fix.notes.0.label', 'General')
            ->where('fix.returnedBy.name', 'SDAO Member A')
            ->where('fix.back.title', 'Track Documents')
            ->etc()
        );
})->with('detail forms');

test('a return that flagged nothing fixable opens the page as a plain, editable form', function () {
    $document = fixReturnedDocument(FormType::OrganizationRenewal, ['general']);

    $this->actingAs($this->student)->withoutVite()
        ->get(fixEditRoute($document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('fix.active', false)->has('fix.items', 0));
});

// ── Nobody else gets in ─────────────────────────────────────────────────────

test('another organization\'s officer cannot open, resend, save a draft for, or upload to the document', function (FormType $type) {
    $document = fixReturnedDocument($type, ['contact_information']);

    $this->actingAs($this->otherOrgStudent)->withoutVite()
        ->get(fixEditRoute($document))
        ->assertForbidden();

    $this->actingAs($this->otherOrgStudent)
        ->put(fixUpdateRoute($document), fixDetailPayload(['contact_person' => 'Hijacked']))
        ->assertForbidden();

    $this->actingAs($this->otherOrgStudent)
        ->postJson(route('documents.fix-draft', $document), ['sections' => ['contact_information' => ['contact_person' => 'x']]])
        ->assertForbidden();

    $this->actingAs($this->otherOrgStudent)
        ->postJson(route('attachments.store'), [
            'document_id' => $document->id,
            'slot_key' => 'by_laws',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();

    expect($document->refresh()->registrationDetail->contact_person)->toBe('Old Person');
})->with('detail forms');

// ── Save draft ──────────────────────────────────────────────────────────────

test('save draft keeps the flagged values, leaves the document alone, and is shown on the page again', function () {
    $document = fixReturnedDocument(FormType::OrganizationRenewal, ['contact_information']);

    $this->actingAs($this->student)
        ->postJson(route('documents.fix-draft', $document), ['sections' => ['contact_information' => ['contact_person' => 'Draft Person']]])
        ->assertOk()
        ->assertJson(['saved' => true]);

    // The returned version is still the baseline "changed" is measured against.
    expect($document->refresh()->registrationDetail->contact_person)->toBe('Old Person');
    expect($document->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->student)->withoutVite()
        ->get(fixEditRoute($document))
        ->assertInertia(fn ($page) => $page->where('fix.draft.contact_information.contact_person', 'Draft Person')->etc());

    // Resending clears it.
    $this->actingAs($this->student)
        ->put(fixUpdateRoute($document), ['contact_person' => 'Final Person', 'contact_no' => '09170000000', 'email_address' => 'old@nu-lipa.edu.ph'])
        ->assertSessionHasNoErrors();
    expect(FixDraft::get($document))->toBeNull();
});

test('save draft follows the same lock: only flagged items, only their own fields', function () {
    $document = fixReturnedDocument(FormType::OrganizationRenewal, ['contact_information']);

    // A section that was not flagged.
    $this->actingAs($this->student)
        ->postJson(route('documents.fix-draft', $document), ['sections' => ['organization_details' => ['purpose_of_organization' => 'x']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locked');

    // A field that is not part of the flagged section.
    $this->actingAs($this->student)
        ->postJson(route('documents.fix-draft', $document), ['sections' => ['contact_information' => ['purpose_of_organization' => 'x']]])
        ->assertUnprocessable();

    expect(FixDraft::get($document))->toBeNull();
});

test('save draft is not offered when nothing on the return is fixable', function () {
    $document = fixReturnedDocument(FormType::OrganizationRenewal, []);

    $this->actingAs($this->student)
        ->postJson(route('documents.fix-draft', $document), ['sections' => []])
        ->assertUnprocessable();
});

// ── The standalone upload endpoint follows the same lock ────────────────────

test('the standalone upload endpoint only takes flagged slots on a returned document', function () {
    $document = fixReturnedDocument(FormType::OrganizationRegistration, ['by_laws']);
    $letter = $document->attachments()->where('slot_key', 'letter_of_intent')->firstOrFail();

    $this->actingAs($this->student)
        ->postJson(route('attachments.store'), [
            'document_id' => $document->id,
            'slot_key' => 'letter_of_intent',
            'file' => UploadedFile::fake()->create('swap.pdf', 10, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locked');
    expect(DocumentAttachment::find($letter->id))->not->toBeNull();

    $this->actingAs($this->student)
        ->deleteJson(route('attachments.destroy', $letter))
        ->assertUnprocessable();
    expect(DocumentAttachment::find($letter->id))->not->toBeNull();

    $this->actingAs($this->student)
        ->postJson(route('attachments.store'), [
            'document_id' => $document->id,
            'slot_key' => 'by_laws',
            'file' => UploadedFile::fake()->create('fresh.pdf', 10, 'application/pdf'),
        ])
        ->assertCreated();
});

// ── Where the resend goes ───────────────────────────────────────────────────

test('the resend button names the step the resubmit really resumes at', function () {
    // A proposal returned by the adviser (step 1) resumes at the adviser, not SDAO.
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: ['title' => 'Coding Night', 'venue' => 'Function Hall', 'activity_date' => '2027-12-10', 'start_time' => '09:00', 'end_time' => '11:00'],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $document] = app(SubmitActivityProposal::class)->execute(actor: $this->student, document: $draft, objectives: 'Goal');
    $this->engine->returnForRevision($document, $this->adviser, 'Fix the goal.', ['objectives']);
    $document->refresh();

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-proposals.edit', $document))
        ->assertInertia(fn ($page) => $page->where('fix.resendLabel', 'Resend to Adviser')->where('fix.role', 'Adviser')->etc());

    // The registration SDAO returned resumes at SDAO.
    $registration = fixReturnedDocument(FormType::OrganizationRegistration, ['contact_information']);
    $this->actingAs($this->student)->withoutVite()
        ->get(route('registrations.edit', $registration))
        ->assertInertia(fn ($page) => $page->where('fix.resendLabel', 'Resend to SDAO')->etc());

    $this->actingAs($this->student)
        ->put(route('registrations.update', $registration), ['contact_person' => 'New Person', 'contact_no' => '09170000000', 'email_address' => 'old@nu-lipa.edu.ph'])
        ->assertSessionHasNoErrors();
    $registration->refresh();
    $step = $registration->workflowTemplate->steps->firstWhere('position', $registration->current_step_position);
    expect($step->role->value)->toBe('sdao_member');
});

// ── After-activity report ───────────────────────────────────────────────────

function fixReturnedReport(array $flagged, array $withoutSlots = []): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $proposalDoc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Approved,
        'submitted_by' => $student->id,
    ]);
    $proposal = ActivityProposal::factory()->create(['document_id' => $proposalDoc->id]);

    $report = Document::factory()->create([
        'form_type' => FormType::AfterActivityReport,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $student->id,
    ]);
    AfterActivityReport::factory()->create([
        'document_id' => $report->id,
        'activity_proposal_id' => $proposal->id,
        'summary' => 'We held it.',
        'outcomes' => 'Good.',
        'participant_count' => 20,
        'activity_chairs' => ['Ana Reyes'],
        'prepared_by' => 'Ana Reyes',
        'event_program' => '9am start.',
        'target_participants_percentage' => 80,
    ]);

    app(AttachmentStorage::class)->storeMany($report, array_diff_key(reportAttachmentFiles(), array_flip($withoutSlots)), $student);
    app(ApprovalEngine::class)->submit($report, $student);
    app(ApprovalEngine::class)->returnForRevision($report->refresh(), $adviser, 'Please revise.', $flagged);

    return $report->refresh();
}

function fixReportPayload(array $overrides = []): array
{
    return [
        'summary' => 'We held it.',
        'outcomes' => 'Good.',
        'participant_count' => 20,
        'activity_chairs' => ['Ana Reyes'],
        'prepared_by' => 'Ana Reyes',
        'event_program' => '9am start.',
        'target_participants_percentage' => 80,
        ...$overrides,
    ];
}

test('report: a locked field and a locked slot are refused', function () {
    $report = fixReturnedReport(['evaluation']);

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), fixReportPayload(['outcomes' => 'Better.', 'summary' => 'Rewritten summary.']))
        ->assertSessionHasErrors('locked');

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), [
            ...fixReportPayload(['outcomes' => 'Better.']),
            'attachments' => ['attendance_sheet' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors('locked');

    expect($report->refresh()->status)->toBe(DocumentStatus::Returned);
    expect($report->afterActivityReport->outcomes)->toBe('Good.');
});

test('report: the flagged evaluation must change, and the page can send just that', function () {
    $report = fixReturnedReport(['evaluation']);

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), fixReportPayload())
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Evaluation.']);

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), ['outcomes' => 'Better.', 'target_participants_percentage' => 90, 'participant_count' => 25])
        ->assertSessionHasNoErrors();

    $report->refresh();
    expect($report->status)->toBe(DocumentStatus::InReview);
    expect($report->afterActivityReport->summary)->toBe('We held it.');
    expect($report->afterActivityReport->target_participants_percentage)->toBe(90);
});

test('report: flagged photos need new photos, which are added to the ones on file', function () {
    $report = fixReturnedReport(['photos']);

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), fixReportPayload())
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Photos (needs a new file).']);

    $this->actingAs($this->student)
        ->put(route('reports.update', $report), [
            ...fixReportPayload(),
            'attachments' => ['photos' => [UploadedFile::fake()->create('more.jpg', 100, 'image/jpeg')]],
        ])
        ->assertSessionHasNoErrors();

    expect($report->refresh()->attachments()->where('slot_key', 'photos')->count())->toBe(2);
});

test('report: Event Details has nothing to edit, so flagging only it leaves the form open', function () {
    $report = fixReturnedReport(['event_details']);

    expect(ReturnedEditLock::for($report))->toBeNull();
    $this->actingAs($this->student)->withoutVite()
        ->get(route('reports.edit', $report))
        ->assertInertia(fn ($page) => $page->where('fix.active', false)->where('fix.notes.0.label', 'Event Details')->etc());
});

// ── Activity proposal ───────────────────────────────────────────────────────

function fixReturnedProposal(array $flagged, ProposalCalendarMode $mode = ProposalCalendarMode::OffCalendar): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: $mode,
        data: ['title' => 'Coding Night', 'venue' => 'Function Hall', 'activity_date' => '2027-12-10', 'start_time' => '09:00', 'end_time' => '11:00'],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $document] = app(SubmitActivityProposal::class)->execute(actor: $student, document: $draft, objectives: 'Goal');

    $document->activityProposal->update([
        'activity_description' => 'Describe it.',
        'criteria_mechanics' => 'Criteria.',
        'program_flow' => 'Flow.',
        'expense_items' => [['material' => 'Tarp', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Ana'],
    ]);

    app(ApprovalEngine::class)->returnForRevision($document->refresh(), $adviser, 'Please revise.', $flagged);

    return $document->refresh();
}

test('proposal: a locked narrative field is refused, and a flagged one must change', function () {
    $document = fixReturnedProposal(['objectives']);

    // Locked: the activity description cannot move.
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'A sharper goal.', 'activity_description' => 'Rewritten.'])
        ->assertSessionHasErrors('locked');

    // Flagged but untouched.
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'Goal'])
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Objectives.']);

    // Just the flagged field, as the page sends it.
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'A sharper goal.'])
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->activityProposal->objectives)->toBe('A sharper goal.');
    expect($document->activityProposal->activity_description)->toBe('Describe it.');
});

test('proposal: a changed venue is refused unless the schedule was flagged, and the calendar choice never moves', function () {
    $document = fixReturnedProposal(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'A sharper goal.', 'venue' => 'Main Gymnasium'])
        ->assertSessionHasErrors('locked');

    expect($document->refresh()->activityProposal->calendarActivity->venue)->toBe('Function Hall');
    expect($document->activityProposal->calendar_mode)->toBe(ProposalCalendarMode::OffCalendar);

    $flagged = fixReturnedProposal(['schedule_venue']);
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $flagged), ['venue' => 'Main Gymnasium'])
        ->assertSessionHasNoErrors();
    expect($flagged->refresh()->activityProposal->calendarActivity->venue)->toBe('Main Gymnasium');
});

test('proposal: a flagged request letter needs a new upload, and the others stay', function () {
    $document = fixReturnedProposal(['request_letter']);
    $survey = $document->attachments()->where('slot_key', 'sample_post_survey_form')->firstOrFail();

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [])
        ->assertSessionHasErrors('flagged');

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [
            'attachments' => ['sample_post_survey_form' => UploadedFile::fake()->create('swap.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors('locked');
    expect(DocumentAttachment::find($survey->id))->not->toBeNull();

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [
            'attachments' => ['request_letter' => UploadedFile::fake()->create('new-letter.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasNoErrors();
    expect($document->refresh()->status)->toBe(DocumentStatus::InReview);
    expect(DocumentAttachment::find($survey->id))->not->toBeNull();
});

test('proposal: Schedule & Venue on an on-calendar proposal has nothing to edit, so the form stays open', function () {
    $document = fixReturnedProposal(['schedule_venue']);
    $document->activityProposal->update(['calendar_mode' => ProposalCalendarMode::OnCalendar]);

    expect(ReturnedEditLock::for($document->refresh()))->toBeNull();
});

// ── Activity calendar ───────────────────────────────────────────────────────

function fixReturnedCalendar(array $flagged): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    ['document' => $document] = app(SubmitActivityCalendar::class)->execute(
        actor: $student,
        organization: $org,
        activities: [
            ['name' => 'Orientation', 'venue' => 'Gymnasium', 'activity_date' => '2027-09-15', 'start_time' => '09:00', 'end_time' => '12:00', 'sdg' => ['no_poverty'], 'participant_program_assigned' => 'All', 'budget' => '100'],
            ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2027-10-01', 'start_time' => '08:00', 'end_time' => '17:00', 'sdg' => ['no_poverty'], 'participant_program_assigned' => 'All', 'budget' => '200'],
        ],
    );

    app(ApprovalEngine::class)->returnForRevision($document, $sdao, 'Please revise.', $flagged);

    return $document->refresh();
}

function fixCalendarRow(array $overrides = []): array
{
    return [
        'name' => 'Hackathon',
        'venue' => 'AVR 1',
        'activity_date' => '2027-10-01',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'sdg' => ['no_poverty'],
        'participant_program_assigned' => 'All',
        'budget' => '200',
        'description' => null,
        ...$overrides,
    ];
}

test('calendar: only the flagged row can change, and the page sends just that row by its position', function () {
    $document = fixReturnedCalendar(['activity_1']);

    $this->actingAs($this->student)
        ->put(route('activity-calendars.update', $document), ['activities' => [1 => fixCalendarRow(['venue' => 'Auditorium'])]])
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    $rows = $document->activityCalendar->activities()->get();
    expect($rows)->toHaveCount(2);
    expect($rows->firstWhere('name', 'Orientation')->venue)->toBe('Gymnasium');
    expect($rows->firstWhere('name', 'Hackathon')->venue)->toBe('Auditorium');
});

test('calendar: a change to a row that was not flagged is refused', function () {
    $document = fixReturnedCalendar(['activity_1']);

    $this->actingAs($this->student)
        ->put(route('activity-calendars.update', $document), ['activities' => [
            0 => fixCalendarRow(['name' => 'Orientation', 'venue' => 'Auditorium', 'activity_date' => '2027-09-15', 'start_time' => '09:00', 'end_time' => '12:00', 'budget' => '100']),
            1 => fixCalendarRow(['venue' => 'Auditorium 2']),
        ]])
        ->assertSessionHasErrors('locked');

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
    expect($document->activityCalendar->activities()->where('name', 'Orientation')->value('venue'))->toBe('Gymnasium');
});

test('calendar: a flagged row left unchanged blocks the resend, and rows cannot be added or dropped', function () {
    $document = fixReturnedCalendar(['activity_1']);

    $this->actingAs($this->student)
        ->put(route('activity-calendars.update', $document), ['activities' => [1 => fixCalendarRow()]])
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Activity 2.']);

    $this->actingAs($this->student)
        ->put(route('activity-calendars.update', $document), ['activities' => [
            1 => fixCalendarRow(['venue' => 'Auditorium']),
            2 => fixCalendarRow(['name' => 'Bonus', 'venue' => 'Elsewhere']),
        ]])
        ->assertSessionHasErrors('locked');

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
    expect($document->activityCalendar->activities()->count())->toBe(2);
});

test('calendar: a return with no flags still replaces the whole calendar', function () {
    $document = fixReturnedCalendar([]);

    $this->actingAs($this->student)
        ->put(route('activity-calendars.update', $document), ['activities' => [
            fixCalendarRow(['name' => 'Orientation', 'venue' => 'Elsewhere', 'activity_date' => '2027-09-15', 'start_time' => '09:00', 'end_time' => '12:00', 'budget' => '100']),
        ]])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->status)->toBe(DocumentStatus::InReview);
    expect($document->activityCalendar->activities()->count())->toBe(1);
});

test('calendar: the edit page names the flagged rows', function () {
    $document = fixReturnedCalendar(['activity_1']);

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-calendars.edit', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('fix.active', true)
            ->where('fix.items.0.key', 'activity_1')
            ->where('fix.items.0.label', 'Activity 2: Hackathon')
            ->where('fix.items.0.kind', 'row')
            ->etc()
        );
});

test('the proposal autosave cannot be used to write to a returned proposal', function () {
    $document = fixReturnedProposal(['objectives']);

    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $document), ['activity_description' => 'Sneaked in through autosave.'])
        ->assertForbidden();

    expect($document->refresh()->activityProposal->activity_description)->toBe('Describe it.');
});
