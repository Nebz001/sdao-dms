<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\AfterActivityReport;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Printing\AfterActivityReportForm;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Covers App\Printing\AfterActivityReportForm::data(): field mapping, Date
 * Submitted deriving from the LATEST submitted/resubmitted transition (not
 * the first), and the Photos filename listing. No signature/approval
 * section exists on this template, so no SignatureBlock coverage here.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

/**
 * Drives a fresh on-calendar proposal through its full 7-step regular chain
 * to Approved, returning the ActivityProposal — mirrors
 * AfterActivityReportExactFieldsTest's fixture (distinct name to avoid the
 * global-function collision that file's own helper would cause).
 */
function printFormApprovedProposal(Organization $org, User $student): ActivityProposal
{
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'AAR Print Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $engine = app(ApprovalEngine::class);
    foreach ([$adviserOne, $chairCs, $deanCcit, $sdaoA, $sdaoB, $asstDir, $acadDir, $execDir] as $approver) {
        $engine->approve($proposalDoc, $approver);
        $proposalDoc->refresh();
    }

    return $proposalDoc->activityProposal()->firstOrFail();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function afterActivityReportPrintDocument(ActivityProposal $proposal, User $submitter, array $overrides = []): Document
{
    $doc = Document::create([
        'form_type' => FormType::AfterActivityReport,
        'variant' => null,
        'title' => "After-Activity Report — {$proposal->title}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $proposal->document->organization_id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);

    AfterActivityReport::create(array_merge([
        'document_id' => $doc->id,
        'activity_proposal_id' => $proposal->id,
        'summary' => 'The activity happened as planned.',
        'outcomes' => 'Outcomes text — not on the paper form.',
        'participant_count' => 42,
        'activity_chairs' => ['Chair One', 'Chair Two'],
        'prepared_by' => 'Preparer Name',
        'event_program' => '9:00 Opening, 10:00 Main, 11:00 Closing.',
        'target_participants_percentage' => 85,
    ], $overrides));

    return $doc;
}

function dataForReport(Document $document): array
{
    $form = app(AfterActivityReportForm::class);
    $document->load($form->eagerLoads());

    return $form->data($document);
}

// ── Field mapping ────────────────────────────────────────────────────────

test('fields map from the report, its linked proposal, and the proposal\'s calendar activity', function () {
    $proposal = printFormApprovedProposal($this->org, $this->studentAlpha);
    $doc = afterActivityReportPrintDocument($proposal, $this->studentAlpha);

    $data = dataForReport($doc);

    expect($data['name_of_event'])->toBe($proposal->title);
    expect($data['date_and_time_of_event'])->toBe('10/30/2026, 09:00 AM – 12:00 PM');
    expect($data['activity_chairs'])->toBe('Chair One, Chair Two');
    expect($data['prepared_by'])->toBe('Preparer Name');
    expect($data['summary'])->toBe('The activity happened as planned.');
    expect($data['program'])->toBe('9:00 Opening, 10:00 Main, 11:00 Closing.');
    expect($data['target_participants_percentage'])->toBe(85);
});

test('outcomes and participant_count are not on the paper form and are omitted from data()', function () {
    $proposal = printFormApprovedProposal($this->org, $this->studentAlpha);
    $doc = afterActivityReportPrintDocument($proposal, $this->studentAlpha);

    $data = dataForReport($doc);

    expect($data)->not->toHaveKey('outcomes');
    expect($data)->not->toHaveKey('participant_count');
});

// ── Date Submitted: latest submitted/resubmitted, not first ─────────────

test('Date Submitted uses the latest submitted-or-resubmitted transition, not the first one', function () {
    $proposal = printFormApprovedProposal($this->org, $this->studentAlpha);
    $doc = afterActivityReportPrintDocument($proposal, $this->studentAlpha);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => '2026-07-01 08:00:00',
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => null,
        'action' => TransitionAction::Returned,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::Returned,
        'step_position' => 1,
        'created_at' => '2026-07-02 08:00:00',
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Resubmitted,
        'from_status' => DocumentStatus::Returned,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => '2026-07-10 08:00:00',
    ]);

    $data = dataForReport($doc);

    // Not the first submission (07/01) — the resubmission that actually
    // went through (07/10, Asia/Manila keeps the same calendar date here).
    expect($data['date_submitted'])->toBe('07/10/2026');
});

// ── Photos: list filenames, don't embed ──────────────────────────────────

test('Photos lists uploaded filenames under the heading rather than embedding images', function () {
    $proposal = printFormApprovedProposal($this->org, $this->studentAlpha);
    $doc = afterActivityReportPrintDocument($proposal, $this->studentAlpha);

    DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => 'photos', 'original_filename' => 'award-ceremony.jpg']);
    DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => 'photos', 'original_filename' => 'group-photo.png']);
    DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => 'evaluation_form']);
    DocumentAttachment::factory()->create(['document_id' => $doc->id, 'slot_key' => 'attendance_sheet']);

    $data = dataForReport($doc);

    expect($data['photo_filenames'])->toBe(['award-ceremony.jpg', 'group-photo.png']);
    expect($data['has_evaluation_form'])->toBeTrue();
    expect($data['has_attendance_sheet'])->toBeTrue();
});

test('Photos, Evaluation Form, and Attendance Sheet all report absent with no attachments', function () {
    $proposal = printFormApprovedProposal($this->org, $this->studentAlpha);
    $doc = afterActivityReportPrintDocument($proposal, $this->studentAlpha);

    $data = dataForReport($doc);

    expect($data['photo_filenames'])->toBe([]);
    expect($data['has_evaluation_form'])->toBeFalse();
    expect($data['has_attendance_sheet'])->toBeFalse();
});
