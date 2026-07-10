<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 7 slice 3 — exact field corrections for the After-Activity
 * Report: Summary rename (`narrative`→`summary`), plus new fields Activity
 * Chair/s, Prepared By, Program (`event_program`), and % Target
 * Participants. Also verifies the display-only fixes (Name of Event, Date
 * and Time of Event, Date Submitted) and that no attachment upload UI was
 * introduced (deferred to Phase 2 item 8).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->reportAction = app(SubmitAfterActivityReport::class);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

/**
 * Drives a fresh on-calendar proposal for Computing Society through its full
 * 7-step regular chain to Approved, returning the ActivityProposal — mirrors
 * SubmitAfterActivityReportTest's fixture.
 */
function exactFieldsApprovedProposal(Organization $org, User $student): ActivityProposal
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
        'name' => 'Exact Fields Test Activity',
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

    expect($proposalDoc->status)->toBe(DocumentStatus::Approved);

    return $proposalDoc->activityProposal()->firstOrFail();
}

function reportStorePayload(array $overrides = []): array
{
    return array_merge([
        'summary' => 'The activity happened as planned.',
        'activity_chairs' => ['Chair One', 'Chair Two'],
        'prepared_by' => 'Preparer Name',
        'event_program' => '9:00 Opening remarks, 10:00 Main session, 11:00 Closing.',
        'target_participants_percentage' => 85,
    ], $overrides);
}

// --- Validation ---------------------------------------------------------

test('store validation rejects a submission missing activity_chairs, prepared_by, event_program, or target_participants_percentage', function () {
    $proposal = exactFieldsApprovedProposal($this->org, $this->studentAlpha);
    $base = array_merge(reportStorePayload(), ['activity_proposal_id' => $proposal->id]);

    foreach (['activity_chairs', 'prepared_by', 'event_program', 'target_participants_percentage'] as $field) {
        $payload = $base;
        unset($payload[$field]);

        $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), $payload);

        $response->assertInvalid([$field]);
    }
});

test('store validation rejects a target_participants_percentage outside 0-100', function () {
    $proposal = exactFieldsApprovedProposal($this->org, $this->studentAlpha);

    $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
        reportStorePayload(['target_participants_percentage' => 150]),
        ['activity_proposal_id' => $proposal->id],
    ));

    $response->assertInvalid(['target_participants_percentage']);
});

// --- Round-trip: submit -> stored -> shown (student + approver) --------

test('Summary, Activity Chair/s, Prepared By, Program, and % Target Participants round-trip through submission and both show pages', function () {
    $proposal = exactFieldsApprovedProposal($this->org, $this->studentAlpha);

    $document = $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        summary: 'The activity happened as planned.',
        activityChairs: ['Chair One', 'Chair Two'],
        preparedBy: 'Preparer Name',
        eventProgram: '9:00 Opening remarks, 10:00 Main session, 11:00 Closing.',
        targetParticipantsPercentage: 85,
    );

    $report = $document->afterActivityReport;
    expect($report->summary)->toBe('The activity happened as planned.');
    expect($report->activity_chairs)->toBe(['Chair One', 'Chair Two']);
    expect($report->prepared_by)->toBe('Preparer Name');
    expect($report->event_program)->toBe('9:00 Opening remarks, 10:00 Main session, 11:00 Closing.');
    expect($report->target_participants_percentage)->toBe(85);

    // Student show page — Name of Event / Date and Time of Event / Date Submitted
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/show')
            ->where('report.summary', 'The activity happened as planned.')
            ->where('report.activity_chairs', ['Chair One', 'Chair Two'])
            ->where('report.prepared_by', 'Preparer Name')
            ->where('report.target_participants_percentage', 85)
            ->where('report.activity.title', 'Exact Fields Test Activity')
            ->where('report.activity.activity_date', '2026-10-30')
            ->where('report.activity.start_time', '09:00')
            ->where('report.activity.end_time', '12:00')
            ->has('document.date_submitted')
        );

    // Approver (SDAO) show page — must see the same fields to make a decision
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.reports.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/reports/show')
            ->where('report.summary', 'The activity happened as planned.')
            ->where('report.activity_chairs', ['Chair One', 'Chair Two'])
            ->where('report.prepared_by', 'Preparer Name')
            ->where('report.target_participants_percentage', 85)
            ->where('report.activity.title', 'Exact Fields Test Activity')
            ->has('document.date_submitted')
        );
});

test('Date Submitted renders on the index list from the document\'s real created_at', function () {
    $proposal = exactFieldsApprovedProposal($this->org, $this->studentAlpha);

    $document = $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        summary: 'The activity happened as planned.',
        activityChairs: ['Chair One'],
        preparedBy: 'Preparer Name',
        eventProgram: 'Program details.',
        targetParticipantsPercentage: 90,
    );

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports.0.created_at')
        );

    expect($document->created_at)->not->toBeNull();
});

// --- Scope guard: no attachment pipeline was introduced ------------------

test('report submission succeeds with no file upload and StoreReportRequest validates no file fields', function () {
    $proposal = exactFieldsApprovedProposal($this->org, $this->studentAlpha);

    $rules = (new StoreReportRequest)->rules();
    $ruleKeys = array_keys($rules);

    foreach (['photos', 'evaluation_form', 'attendance_sheet', 'attachments'] as $attachmentField) {
        expect($ruleKeys)->not->toContain($attachmentField);
    }

    $response = $this->actingAs($this->studentAlpha)->post(route('reports.store'), array_merge(
        reportStorePayload(),
        ['activity_proposal_id' => $proposal->id],
    ));

    $response->assertRedirect();
});
