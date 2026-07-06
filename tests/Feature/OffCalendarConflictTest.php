<?php

use App\ActivityProposals\ResubmitActivityProposal;
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
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->resubmitProposal = app(ResubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

function offCalApprovedActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Existing Approved Calendar',
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
        'name' => 'Existing Approved Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function offCalInReviewActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Existing InReview Calendar',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
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
        'name' => 'Existing InReview Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function offCalSubmitDraft(User $student, Organization $org, string $venue, string $date, string $start, string $end): Document
{
    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Conflict Test Activity',
            'venue' => $venue,
            'activity_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'term' => 'first_term',
        ],
    );

    return app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    )['document'];
}

// ── Hard-block tests ─────────────────────────────────────────────────────────

test('off-calendar submit overlapping an Approved activity is a hard block', function () {
    offCalApprovedActivity('Hall A', '2026-10-20', '09:00', '12:00');

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Conflicting Activity',
            'venue' => 'Hall A',
            'activity_date' => '2026-10-20',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'term' => 'first_term',
        ],
    );

    expect(fn () => $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    ))->toThrow(ValidationException::class);

    // Draft must NOT have entered the chain
    expect($draft->fresh()->status)->toBe(DocumentStatus::Draft);
});

// ── Non-blocking tentative warning ──────────────────────────────────────────

test('off-calendar submit overlapping an InReview activity submits with warning', function () {
    offCalInReviewActivity('Hall A', '2026-10-20', '09:00', '12:00');

    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Tentative Overlap',
            'venue' => 'Hall A',
            'activity_date' => '2026-10-20',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'term' => 'first_term',
        ],
    );

    $result = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Submitted (non-blocking)
    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    // Warning present
    expect($result['warnings'])->not->toBeEmpty();
});

// ── Non-overlapping cases ────────────────────────────────────────────────────

test('off-calendar submit at a different venue is allowed (no conflict)', function () {
    offCalApprovedActivity('Hall A', '2026-10-20', '09:00', '12:00');

    $result = $this->submitProposal->execute(
        actor: $this->student,
        document: $this->startDraft->execute(
            actor: $this->student,
            organization: $this->org,
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'Different Venue',
                'venue' => 'Hall B', // different venue
                'activity_date' => '2026-10-20',
                'start_time' => '10:00',
                'end_time' => '13:00',
                'term' => 'first_term',
            ],
        ),
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['warnings'])->toBeEmpty();
});

test('off-calendar submit with touching (not overlapping) times is allowed', function () {
    offCalApprovedActivity('Hall A', '2026-10-20', '09:00', '10:00');

    $result = $this->submitProposal->execute(
        actor: $this->student,
        document: $this->startDraft->execute(
            actor: $this->student,
            organization: $this->org,
            mode: ProposalCalendarMode::OffCalendar,
            data: [
                'title' => 'Touching Time',
                'venue' => 'Hall A',
                'activity_date' => '2026-10-20',
                'start_time' => '10:00', // starts exactly when Approved ends
                'end_time' => '12:00',
                'term' => 'first_term',
            ],
        ),
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['warnings'])->toBeEmpty();
});

// ── Approved proposal hard-blocks subsequent submissions ─────────────────────

test('once proposal is Approved its off-cal activity hard-blocks a later submission', function () {
    // Submit and fully approve an off-calendar proposal
    $approvedProposalDoc = offCalSubmitDraft(
        $this->student, $this->org, 'Hall C', '2026-11-10', '09:00', '11:00'
    );

    // Advance through the off-calendar chain for Computing Society:
    // [SdaoMember(×2), Adviser, ProgramChair, Dean, AsstDir, AcadDir, ExecDir]
    $adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $asstDir = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $acadDir = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $execDir = User::where('email', 'executive-director@sdao.test')->firstOrFail();

    $this->engine->approve($approvedProposalDoc, $this->sdaoA);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $this->sdaoB);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $adviser);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $chair);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $dean);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $asstDir);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $acadDir);
    $approvedProposalDoc->refresh();
    $this->engine->approve($approvedProposalDoc, $execDir);
    $approvedProposalDoc->refresh();

    expect($approvedProposalDoc->status)->toBe(DocumentStatus::Approved);

    // Now a second student tries to submit for the same venue/date/time
    $otherOrg = Organization::where('name', 'IT Guild')->firstOrFail();
    $otherStudent = User::where('email', 'student-beta@sdao.test')->firstOrFail();

    $conflictingDraft = app(StartProposalDraft::class)->execute(
        actor: $otherStudent,
        organization: $otherOrg,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Conflicting Later Submission',
            'venue' => 'Hall C',
            'activity_date' => '2026-11-10',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'term' => 'first_term',
        ],
    );

    expect(fn () => app(SubmitActivityProposal::class)->execute(
        actor: $otherStudent,
        document: $conflictingDraft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    ))->toThrow(ValidationException::class);
});

// ── Draft CalendarActivity is invisible to the conflict checker ──────────────

test('off-calendar proposal CalendarActivity is invisible to checker while Draft', function () {
    // Start an off-calendar draft (activity is Draft status — invisible)
    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Draft Activity',
            'venue' => 'Hall D',
            'activity_date' => '2026-11-20',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
        ],
    );

    // A rival submission to the same slot should not see a conflict (since draft is invisible)
    $otherOrg = Organization::where('name', 'IT Guild')->firstOrFail();
    $otherStudent = User::where('email', 'student-beta@sdao.test')->firstOrFail();

    $rivalDraft = $this->startDraft->execute(
        actor: $otherStudent,
        organization: $otherOrg,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Rival Activity',
            'venue' => 'Hall D',
            'activity_date' => '2026-11-20',
            'start_time' => '09:30',
            'end_time' => '10:30',
            'term' => 'first_term',
        ],
    );

    $result = app(SubmitActivityProposal::class)->execute(
        actor: $otherStudent,
        document: $rivalDraft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // No conflict because the first proposal is still Draft (invisible)
    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['warnings'])->toBeEmpty();
});
