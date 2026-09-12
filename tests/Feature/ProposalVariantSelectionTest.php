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
use App\Models\WorkflowTemplate;
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
    $this->chessClub = Organization::where('name', 'University Chess Club')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentGamma = User::where('email', 'student-gamma@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentEpsilon = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail();
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
        attachmentFiles: proposalStepOneAttachmentFiles(),
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

test('college-less org + on-calendar → ExtraCurricularOnCalendar variant', function () {
    $variant = $this->resolver->resolve($this->chessClub, ProposalCalendarMode::OnCalendar);
    expect($variant)->toBe(ProposalVariant::ExtraCurricularOnCalendar);
});

test('college-less org + off-calendar → ExtraCurricularOffCalendar variant', function () {
    $variant = $this->resolver->resolve($this->chessClub, ProposalCalendarMode::OffCalendar);
    expect($variant)->toBe(ProposalVariant::ExtraCurricularOffCalendar);
});

// ── Integration: submitted document binds correct template ─────────────────

test('regular on-calendar: submitted doc binds template with adviser-first step', function () {
    $activity = proposalApprovedActivity($this->computingSociety);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
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

test('regular off-calendar: submitted doc binds template with adviser-first step, same as on-calendar', function () {
    $draft = startOffCalendarDraft($this->studentAlpha, $this->computingSociety);

    $result = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::RegularOffCalendar);

    $firstStep = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->where('position', 1)
        ->firstOrFail();

    expect($firstStep->role)->toBe(Role::Adviser);
});

test('SHS on-calendar: submitted doc binds template with adviser then principal', function () {
    $activity = proposalApprovedActivity($this->shsCouncil);

    $draft = $this->startDraft->execute(
        actor: $this->studentGamma,
        organization: $this->shsCouncil,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentGamma,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
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

test('SHS off-calendar: submitted doc binds template with adviser then principal then SDAO, same as on-calendar', function () {
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
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentGamma,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
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

    expect($firstStep->role)->toBe(Role::Adviser);
    expect($secondStep->role)->toBe(Role::Principal);
    expect($thirdStep->role)->toBe(Role::SdaoMember);
});

test('college-less on-calendar: submitted doc binds template with adviser then SDAO, skipping chair/dean', function () {
    $activity = proposalApprovedActivity($this->chessClub);

    $draft = $this->startDraft->execute(
        actor: $this->studentEpsilon,
        organization: $this->chessClub,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentEpsilon,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::ExtraCurricularOnCalendar);

    $steps = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->orderBy('position')
        ->pluck('role');

    // No ProgramChair, no Dean, no Principal — straight from adviser to SDAO.
    expect($steps->all())->toBe([
        Role::Adviser,
        Role::SdaoMember,
        Role::AssistantDirectorAcademicServices,
        Role::AcademicDirector,
        Role::ExecutiveDirector,
    ]);
});

test('college-less off-calendar: submitted doc binds template with adviser then SDAO, skipping chair/dean, same as on-calendar', function () {
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentEpsilon,
        organization: $this->chessClub,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Chess Club Off Calendar Test',
            'venue' => 'Student Lounge',
            'activity_date' => '2026-11-06',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $result = $this->submitProposal->execute(
        actor: $this->studentEpsilon,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    $doc = $result['document'];
    expect($doc->variant)->toBe(ProposalVariant::ExtraCurricularOffCalendar);

    $steps = WorkflowStep::where('workflow_template_id', $doc->workflow_template_id)
        ->orderBy('position')
        ->pluck('role');

    expect($steps->all())->toBe([
        Role::Adviser,
        Role::SdaoMember,
        Role::AssistantDirectorAcademicServices,
        Role::AcademicDirector,
        Role::ExecutiveDirector,
    ]);
});

// ── Regression: on-calendar and off-calendar chains must be identical ──────
// Group E backlog — off-calendar previously relocated SDAO to the front;
// confirmed with the client that calendar status must have NO effect on
// chain order at all. These assert role-list equality directly, rather than
// pinning today's order by hand, so a future accidental re-introduction of
// an on/off-calendar branch in the chain shape is caught regardless of which
// order someone picks.

test('on-calendar and off-calendar chains are identical for every school structure', function () {
    $pairs = [
        [ProposalVariant::RegularOnCalendar, ProposalVariant::RegularOffCalendar],
        [ProposalVariant::ShsOnCalendar, ProposalVariant::ShsOffCalendar],
        [ProposalVariant::ExtraCurricularOnCalendar, ProposalVariant::ExtraCurricularOffCalendar],
    ];

    foreach ($pairs as [$onVariant, $offVariant]) {
        $onTemplate = WorkflowTemplate::where('form_type', FormType::ActivityProposal)
            ->where('variant', $onVariant)
            ->firstOrFail();
        $offTemplate = WorkflowTemplate::where('form_type', FormType::ActivityProposal)
            ->where('variant', $offVariant)
            ->firstOrFail();

        $onShape = WorkflowStep::where('workflow_template_id', $onTemplate->id)
            ->orderBy('position')
            ->get(['role', 'required_approvals'])
            ->map(fn ($s) => [$s->role, $s->required_approvals])
            ->all();

        $offShape = WorkflowStep::where('workflow_template_id', $offTemplate->id)
            ->orderBy('position')
            ->get(['role', 'required_approvals'])
            ->map(fn ($s) => [$s->role, $s->required_approvals])
            ->all();

        expect($offShape)->toBe($onShape);
    }
});
