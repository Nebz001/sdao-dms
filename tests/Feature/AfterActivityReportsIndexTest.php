<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
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

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@sdao.test')->firstOrFail(); // secretary, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // president, IT Guild
});

/**
 * Approves an on-calendar proposal for the given org (regular-school 7-step
 * chain) and files a report against it, actor submitting.
 */
function submitReportForOrg(User $actor, Organization $org): void
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
        'name' => 'Index Test Activity',
        'venue' => 'Main Hall',
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

    $proposal = $proposalDoc->activityProposal()->firstOrFail();

    app(SubmitAfterActivityReport::class)->execute(
        actor: $actor,
        proposal: $proposal,
        summary: 'The activity happened as planned.',
    );
}

test('officer sees their org report in the index', function () {
    submitReportForOrg($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports', 1)
            ->where('reports.0.organization.name', 'Computing Society')
        );
});

test('both president and secretary of the same org see the same report', function () {
    submitReportForOrg($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentDelta)
        ->withoutVite()
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports', 1)
        );
});

test('an org officer does not see another org\'s report', function () {
    submitReportForOrg($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentBeta)
        ->withoutVite()
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->has('reports', 0)
        );
});
