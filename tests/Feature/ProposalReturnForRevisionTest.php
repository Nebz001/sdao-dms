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
use App\Models\DocumentStepApproval;
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

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();
});

function returnTestApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'Return Test Activity',
        'venue' => 'Return Hall',
        'activity_date' => '2026-12-05',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

function advanceOnCalendarToStep(Document $doc, ApprovalEngine $engine, int $targetStep, array $stepApprovers): void
{
    foreach ($stepApprovers as $position => $approver) {
        $doc->refresh();
        if ($doc->current_step_position >= $targetStep) {
            break;
        }
        if ($doc->current_step_position === $position) {
            $engine->approve($doc, $approver);
        }
    }
}

// ── Headline test: return at academic director (step 6) ────────────────────

test('return at step 6 keeps lower-step approvals and holds position at 6', function () {
    $activity = returnTestApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Advance steps 1–5
    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    $this->engine->approve($doc, $this->chair);
    $doc->refresh();
    $this->engine->approve($doc, $this->dean);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    $this->engine->approve($doc, $this->asstDir);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(6);

    $lowerApprovalsBefore = DocumentStepApproval::where('document_id', $doc->id)
        ->whereIn('step_position', [1, 2, 3, 4, 5])
        ->count();

    expect($lowerApprovalsBefore)->toBeGreaterThan(0);

    // Academic director returns
    $this->engine->returnForRevision($doc, $this->acadDir, 'Please expand section 3.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->current_step_position)->toBe(6);

    // Steps 1–5 approvals must persist
    $lowerApprovalsAfter = DocumentStepApproval::where('document_id', $doc->id)
        ->whereIn('step_position', [1, 2, 3, 4, 5])
        ->count();

    expect($lowerApprovalsAfter)->toBe($lowerApprovalsBefore);

    // Step-6 approval row cleared
    expect(DocumentStepApproval::where('document_id', $doc->id)->where('step_position', 6)->count())->toBe(0);
});

test('resubmit after step-6 return resumes at step 6, not step 1', function () {
    $activity = returnTestApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    $this->engine->approve($doc, $this->chair);
    $doc->refresh();
    $this->engine->approve($doc, $this->dean);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    $this->engine->approve($doc, $this->asstDir);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->acadDir, 'Needs changes.');
    $doc->refresh();
    $this->engine->resubmit($doc, $this->student);
    $doc->refresh();

    // Resumes at step 6, NOT step 1
    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(6);
});

test('after step-6 return and resubmit, acad dir approves → advances to exec dir then Approved', function () {
    $activity = returnTestApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    $this->engine->approve($doc, $this->chair);
    $doc->refresh();
    $this->engine->approve($doc, $this->dean);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    $this->engine->approve($doc, $this->asstDir);
    $doc->refresh();
    $this->engine->returnForRevision($doc, $this->acadDir, 'Needs changes.');
    $doc->refresh();
    $this->engine->resubmit($doc, $this->student);
    $doc->refresh();

    // Acad dir re-approves
    $this->engine->approve($doc, $this->acadDir);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(7); // exec dir

    $this->engine->approve($doc, $this->execDir);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
});

// ── Mid-low return: dean (step 3) ─────────────────────────────────────────────

test('return at dean (step 3) keeps steps 1–2 and resumes at step 3', function () {
    $activity = returnTestApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    $this->engine->approve($doc, $this->chair);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(3);

    $this->engine->returnForRevision($doc, $this->dean, 'Missing signatures.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->current_step_position)->toBe(3);

    // Steps 1–2 persist
    $persistedApprovals = DocumentStepApproval::where('document_id', $doc->id)
        ->whereIn('step_position', [1, 2])
        ->count();

    expect($persistedApprovals)->toBe(2);

    // Step 3 cleared
    expect(DocumentStepApproval::where('document_id', $doc->id)->where('step_position', 3)->count())->toBe(0);

    // Resubmit → resumes at step 3, not step 1
    $this->engine->resubmit($doc, $this->student);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(3);
    expect($doc->status)->toBe(DocumentStatus::InReview);
});
