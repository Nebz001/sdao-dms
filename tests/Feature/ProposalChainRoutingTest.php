<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\ApprovalNotification;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->shsCouncil = Organization::where('name', 'SHS Student Council')->firstOrFail();

    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->studentGamma = User::where('email', 'student-gamma@sdao.test')->firstOrFail();

    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->adviserShs = User::where('email', 'adviser-shs@sdao.test')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->principalShs = User::where('email', 'principal-shs@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();
});

function chainApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'Chain Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

// ── Regular on-calendar: 7-step chain ────────────────────────────────────────

test('regular on-calendar full chain: adviser → chair → dean → SDAO(×2) → asstDir → acadDir → execDir → Approved', function () {
    $activity = chainApprovedActivity($this->computingSociety);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Step 1: Adviser
    expect($doc->current_step_position)->toBe(1);
    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();

    // Step 2: Program Chair
    expect($doc->current_step_position)->toBe(2);
    $this->engine->approve($doc, $this->chairCs);
    $doc->refresh();

    // Step 3: Dean
    expect($doc->current_step_position)->toBe(3);
    $this->engine->approve($doc, $this->deanCcit);
    $doc->refresh();

    // Step 4: SDAO (needs both members — first does not advance)
    expect($doc->current_step_position)->toBe(4);
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(4); // quorum not met
    expect($doc->status)->toBe(DocumentStatus::InReview);

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    // Step 5: Asst. Director
    expect($doc->current_step_position)->toBe(5);
    $this->engine->approve($doc, $this->asstDir);
    $doc->refresh();

    // Step 6: Academic Director
    expect($doc->current_step_position)->toBe(6);
    $this->engine->approve($doc, $this->acadDir);
    $doc->refresh();

    // Step 7: Executive Director → Approved
    expect($doc->current_step_position)->toBe(7);
    $this->engine->approve($doc, $this->execDir);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('notification fired to each next-step approver on every hand-off (invariant #9)', function () {
    $activity = chainApprovedActivity($this->computingSociety);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // On submit: adviser (step 1) is notified
    expect(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $this->adviserOne->id)->exists())->toBeTrue();

    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();

    // After adviser approves: program chair (step 2) is notified
    expect(ApprovalNotification::where('document_id', $doc->id)->where('user_id', $this->chairCs->id)->exists())->toBeTrue();
});

test('SDAO single approval (split) does not advance the document', function () {
    $activity = chainApprovedActivity($this->computingSociety);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Advance to SDAO step (step 4)
    $this->engine->approve($doc, $this->adviserOne);
    $doc->refresh();
    $this->engine->approve($doc, $this->chairCs);
    $doc->refresh();
    $this->engine->approve($doc, $this->deanCcit);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(4);

    // One SDAO approves — should NOT advance
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(4);
    expect($doc->status)->toBe(DocumentStatus::InReview);
});

// ── SHS on-calendar: 6-step chain (principal instead of chair+dean) ──────────

test('SHS on-calendar: adviser → principal → SDAO(×2) → asstDir → acadDir → execDir → Approved', function () {
    $activity = chainApprovedActivity($this->shsCouncil);

    $draft = $this->startDraft->execute(
        actor: $this->studentGamma,
        organization: $this->shsCouncil,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentGamma,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Step 1: Adviser
    $this->engine->approve($doc, $this->adviserShs);
    $doc->refresh();

    // Step 2: Principal (replaces chair + dean)
    expect($doc->current_step_position)->toBe(2);
    $this->engine->approve($doc, $this->principalShs);
    $doc->refresh();

    // Step 3: SDAO (both)
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    // Step 4: Asst. Director
    $this->engine->approve($doc, $this->asstDir);
    $doc->refresh();

    // Step 5: Academic Director
    $this->engine->approve($doc, $this->acadDir);
    $doc->refresh();

    // Step 6: Executive Director → Approved
    $this->engine->approve($doc, $this->execDir);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
});
