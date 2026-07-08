<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->reportAction = app(SubmitAfterActivityReport::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();
});

/**
 * Approved on-calendar activity source for a proposal (mirrors the pattern
 * used in ProposalChainRoutingTest — an already-Approved calendar).
 */
function reportSourceApprovedActivity(Organization $org): CalendarActivity
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

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Report Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

/**
 * Drives a fresh on-calendar proposal for Computing Society through its full
 * 7-step regular chain to Approved, returning the ActivityProposal.
 */
function approvedProposalForComputingSociety(Organization $org, User $student): ActivityProposal
{
    $activity = reportSourceApprovedActivity($org);

    $startDraft = app(StartProposalDraft::class);
    $submitProposal = app(SubmitActivityProposal::class);
    $engine = app(ApprovalEngine::class);

    $adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();

    $draft = $startDraft->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $submitProposal->execute(
        actor: $student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $engine->approve($doc, $adviserOne);
    $doc->refresh();
    $engine->approve($doc, $chairCs);
    $doc->refresh();
    $engine->approve($doc, $deanCcit);
    $doc->refresh();
    $engine->approve($doc, $sdaoA);
    $doc->refresh();
    $engine->approve($doc, $sdaoB);
    $doc->refresh();
    $engine->approve($doc, $asstDir);
    $doc->refresh();
    $engine->approve($doc, $acadDir);
    $doc->refresh();
    $engine->approve($doc, $execDir);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);

    return $doc->activityProposal()->firstOrFail();
}

test('a report cannot be filed against a proposal that is not approved', function () {
    $activity = reportSourceApprovedActivity($this->org);
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );
    $proposal = $doc->activityProposal()->firstOrFail();

    expect($doc->status)->toBe(DocumentStatus::InReview); // not yet Approved

    expect(fn () => $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'The activity happened.',
    ))->toThrow(ValidationException::class);
});

test('an officer of the activity\'s org can submit a report against an approved proposal', function () {
    $proposal = approvedProposalForComputingSociety($this->org, $this->studentAlpha);

    $report = $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'The activity happened as planned.',
        outcomes: 'Great turnout.',
        participantCount: 120,
    );

    expect($report->status)->toBe(DocumentStatus::InReview);
    expect($report->form_type)->toBe(FormType::AfterActivityReport);
    expect($report->afterActivityReport->activity_proposal_id)->toBe($proposal->id);
    expect($report->afterActivityReport->participant_count)->toBe(120);
});

test('an officer of a different org cannot submit a report for this activity', function () {
    $proposal = approvedProposalForComputingSociety($this->org, $this->studentAlpha);
    $outsiderOfficer = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // president, IT Guild

    expect(fn () => $this->reportAction->execute(
        actor: $outsiderOfficer,
        proposal: $proposal,
        narrative: 'Attempting to report someone else\'s activity.',
    ))->toThrow(AuthorizationException::class);
});

test('at most one non-rejected report may exist per proposal', function () {
    $proposal = approvedProposalForComputingSociety($this->org, $this->studentAlpha);

    $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'First report.',
    );

    expect(fn () => $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'Second report attempt.',
    ))->toThrow(ValidationException::class);
});

test('a rejected report frees the slot — a new report for the same proposal is allowed', function () {
    $proposal = approvedProposalForComputingSociety($this->org, $this->studentAlpha);

    $firstReport = $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'First report.',
    );

    $this->engine->reject($firstReport, $this->sdaoA, 'Incomplete.');
    $firstReport->refresh();
    expect($firstReport->status)->toBe(DocumentStatus::Rejected);

    $secondReport = $this->reportAction->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'Second report after rejection.',
    );

    expect($secondReport->status)->toBe(DocumentStatus::InReview);
    expect($secondReport->id)->not->toBe($firstReport->id);
});
