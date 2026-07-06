<?php

use App\ActivityProposals\ProposalVariantResolver;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->resolver = app(ProposalVariantResolver::class);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->shsCouncil = Organization::where('name', 'SHS Student Council')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentGamma = User::where('email', 'student-gamma@sdao.test')->firstOrFail();
});

function proposalApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'Test Activity',
        'venue' => 'Venue A',
        'activity_date' => '2026-10-01',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

function startOffCalendarDraft(User $student, Organization $org): Document
{
    return app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Off Calendar Test',
            'venue' => 'Room 101',
            'activity_date' => '2026-11-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
        ],
    );
}

// ── Resolver unit tests ────────────────────────────────────────────────────

test('regular org + on-calendar → RegularOnCalendar variant', function () {
    $variant = $this->resolver->resolve($this->computingSociety, ProposalCalendarMode::OnCalendar);
    expect($variant)->toBe(ProposalVariant::RegularOnCalendar);
});

test('regular org + off-calendar → RegularOffCalendar variant', function () {
    $variant = $this->resolver->resolve($this->computingSociety, ProposalCalendarMode::OffCalendar);
    expect($variant)->toBe(ProposalVariant::RegularOffCalendar);
});

test('SHS org + on-calendar → ShsOnCalendar variant', function () {
    $variant = $this->resolver->resolve($this->shsCouncil, ProposalCalendarMode::OnCalendar);
    expect($variant)->toBe(ProposalVariant::ShsOnCalendar);
});

test('SHS org + off-calendar → ShsOffCalendar variant', function () {
    $variant = $this->resolver->resolve($this->shsCouncil, ProposalCalendarMode::OffCalendar);
    expect($variant)->toBe(ProposalVariant::ShsOffCalendar);
});

// ── Integration: submitted document binds correct template ─────────────────

test('regular on-calendar: submitted doc binds template with adviser-first step', function () {
    $activity = proposalApprovedActivity($this->computingSociety);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::RegularOnCalendar);
    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);

    $firstStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 1)
        ->firstOrFail();

    expect($firstStep->role)->toBe(Role::Adviser);
});

test('regular off-calendar: submitted doc binds template with SDAO-first step', function () {
    $draft = startOffCalendarDraft($this->studentAlpha, $this->computingSociety);

    $result = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::RegularOffCalendar);

    $firstStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 1)
        ->firstOrFail();

    expect($firstStep->role)->toBe(Role::SdaoMember);
});

test('SHS on-calendar: submitted doc binds template with adviser then principal', function () {
    $activity = proposalApprovedActivity($this->shsCouncil);

    $draft = $this->startDraft->execute(
        actor: $this->studentGamma,
        organization: $this->shsCouncil,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentGamma,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::ShsOnCalendar);

    $firstStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 1)
        ->firstOrFail();

    $secondStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 2)
        ->firstOrFail();

    expect($firstStep->role)->toBe(Role::Adviser);
    expect($secondStep->role)->toBe(Role::Principal); // no ProgramChair or Dean
});

test('SHS off-calendar: submitted doc binds template with SDAO-first then adviser then principal', function () {
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentGamma,
        organization: $this->shsCouncil,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'SHS Off Calendar Test',
            'venue' => 'SHS Gym',
            'activity_date' => '2026-11-05',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
        ],
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentGamma,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::ShsOffCalendar);

    $firstStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 1)
        ->firstOrFail();
    $secondStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 2)
        ->firstOrFail();
    $thirdStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 3)
        ->firstOrFail();

    expect($firstStep->role)->toBe(Role::SdaoMember);
    expect($secondStep->role)->toBe(Role::Adviser);
    expect($thirdStep->role)->toBe(Role::Principal);
});
