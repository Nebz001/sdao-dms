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

/**
 * Verifies the literal query conditions behind AfterActivityReportController::create()
 * (the report picker) — confirmed with the user as two INDEPENDENT conditions:
 *   (i)  approved proposals belonging to the CURRENT org only
 *   (ii) excludes proposals that already have a non-rejected report
 * A proposal whose only report was Rejected must REAPPEAR in the picker.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail();
});

/**
 * Drives a fresh on-calendar proposal for the given org through its full
 * approval chain to Approved, returning the ActivityProposal.
 */
function pickerApprovedProposal(Organization $org, User $actor, string $activityName): ActivityProposal
{
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
        'name' => $activityName,
        'venue' => 'Main Hall '.$activityName,
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $actor,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $actor,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $adviserEmail = $org->name === 'IT Guild' ? 'adviser-two@sdao.test' : 'adviser-one@sdao.test';
    $chairEmail = $org->name === 'IT Guild' ? 'chair-it@sdao.test' : 'chair-cs@sdao.test';

    foreach ([
        $adviserEmail,
        $chairEmail,
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

    return $proposalDoc->activityProposal()->firstOrFail();
}

test('(i) picker includes an approved proposal with no report yet', function () {
    pickerApprovedProposal($this->computingSociety, $this->studentAlpha, 'Untouched Activity');

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/create')
            ->has('eligibleProposals', 1)
            ->where('eligibleProposals.0.activity.name', 'Untouched Activity')
        );
});

test('(i) picker excludes an approved proposal belonging to a DIFFERENT org', function () {
    pickerApprovedProposal($this->itGuild, $this->studentBeta, 'IT Guild Activity');

    // studentAlpha is an officer of Computing Society, not IT Guild.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/create')
            ->has('eligibleProposals', 0)
        );
});

test('(ii) picker excludes a proposal that already has a non-rejected (e.g. InReview) report', function () {
    $proposal = pickerApprovedProposal($this->computingSociety, $this->studentAlpha, 'Already Reported Activity');

    app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'First report, still under review.',
    );

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/create')
            ->has('eligibleProposals', 0)
        );
});

test('(ii) a proposal whose only report was REJECTED reappears in the picker — rejected does not permanently block', function () {
    $proposal = pickerApprovedProposal($this->computingSociety, $this->studentAlpha, 'Rejected Report Activity');
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    $report = app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'First report attempt.',
    );

    $this->engine->reject($report, $sdaoA, 'Not enough detail.');
    $report->refresh();
    expect($report->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/create')
            ->has('eligibleProposals', 1)
            ->where('eligibleProposals.0.activity.name', 'Rejected Report Activity')
        );
});

test('(i)+(ii) combined: multiple orgs and report states — picker returns exactly the eligible set', function () {
    // Computing Society: one untouched (eligible), one with a live report (excluded).
    pickerApprovedProposal($this->computingSociety, $this->studentAlpha, 'CS Untouched');
    $csReported = pickerApprovedProposal($this->computingSociety, $this->studentAlpha, 'CS Already Reported');
    app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $csReported,
        narrative: 'Live report.',
    );

    // IT Guild: approved proposal, eligible for IT Guild but must NOT appear for Computing Society.
    pickerApprovedProposal($this->itGuild, $this->studentBeta, 'IT Guild Untouched');

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/create')
            ->has('eligibleProposals', 1)
            ->where('eligibleProposals.0.activity.name', 'CS Untouched')
        );
});
