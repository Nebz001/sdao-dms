<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicYear;
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
 * Drives a fresh on-calendar proposal for Computing Society through its full
 * 7-step regular chain to Approved, then submits an after-activity report
 * against it, returning the (InReview) report Document.
 */
function submittedReportForComputingSociety(): Document
{
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $engine = app(ApprovalEngine::class);

    $calendarDoc = Document::create([
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
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Review Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

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

    foreach ([
        'adviser-one@sdao.test',
        'chair-cs@sdao.test',
        'dean-ccit@sdao.test',
        'sdao-a@sdao.test',
        'sdao-b@sdao.test',
        'asst-director@sdao.test',
        'academic-director@sdao.test',
        'executive-director@sdao.test',
    ] as $email) {
        $engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    expect($proposalDoc->status)->toBe(DocumentStatus::Approved);

    $proposal = $proposalDoc->activityProposal()->firstOrFail();

    return app(SubmitAfterActivityReport::class)->execute(
        actor: $student,
        proposal: $proposal,
        narrative: 'The activity happened as planned.',
        outcomes: 'Great turnout.',
        participantCount: 100,
    );
}

test('first SDAO approve is partial — report stays InReview', function () {
    $doc = submittedReportForComputingSociety();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
});

test('second SDAO approve completes the report — Approved', function () {
    $doc = submittedReportForComputingSociety();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('reject terminates the report', function () {
    $doc = submittedReportForComputingSociety();

    $this->engine->reject($doc, $this->sdaoA, 'Not sufficient detail.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
    expect($doc->current_step_position)->toBeNull();
});

test('return sends the report back for revision', function () {
    $doc = submittedReportForComputingSociety();

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please add participant numbers.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
});

test('non-SDAO user cannot approve a report', function () {
    $doc = submittedReportForComputingSociety();

    expect(fn () => $this->engine->approve($doc, $this->outsider))
        ->toThrow(UnauthorizedApproverException::class);
});

test('review show endpoint returns the report with its linked activity and history', function () {
    $doc = submittedReportForComputingSociety();

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.reports.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/reports/show')
            ->has('document')
            ->has('report')
            ->has('report.activity.title')
            ->has('history')
        );
});
