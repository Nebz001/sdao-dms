<?php

use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use App\Notifications\DocumentOutcomeNotification;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
});

function actionsTestApprovedActivity(Organization $org, string $venue = 'Actions Test Hall', string $date = '2026-11-15', string $start = '09:00', string $end = '11:00', string $name = 'Actions Test Event'): CalendarActivity
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
        'name' => $name,
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function actionsTestSubmitOnCalendar($test, string $title = 'Actions Test Event'): Document
{
    $activity = actionsTestApprovedActivity($test->org, name: $title);

    $draft = $test->startDraft->execute(
        actor: $test->student,
        organization: $test->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

function actionsTestSubmitOffCalendar($test, string $venue, string $date, string $start, string $end): Document
{
    $draft = $test->startDraft->execute(
        actor: $test->student,
        organization: $test->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Off-Calendar Actions Test',
            'venue' => $venue,
            'activity_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'term' => 'first_term',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    return $test->submitProposal->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    )['document'];
}

function actionsTestToken(User $user): string
{
    return $user->createToken('Phone')->plainTextToken;
}

// ── Approve ──────────────────────────────────────────────────────────────────

test('approve goes through the real ApprovalEngine and notifies the next approver', function () {
    Notification::fake();

    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/approve", []);

    $response->assertOk();
    $response->assertJson(['current_stage' => 'program_chair_review', 'current_step' => 2]);

    $doc->refresh();
    expect($doc->current_step_position)->toBe(2);
    expect(DocumentTransition::where('document_id', $doc->id)->where('action', TransitionAction::Approved->value)->exists())->toBeTrue();
    expect(DocumentTransition::where('document_id', $doc->id)->where('action', TransitionAction::Advanced->value)->exists())->toBeTrue();

    Notification::assertSentTo($this->chair, ApproverHandOffNotification::class);
});

test('a partial SDAO approval keeps the stage and reports 1 of 2, with no notification yet', function () {
    Notification::fake();

    $doc = actionsTestSubmitOnCalendar($this);
    foreach (['adviser-one@nu-lipa.edu.ph', 'chair-cs@nu-lipa.edu.ph', 'dean-ccit@nu-lipa.edu.ph'] as $email) {
        $this->engine->approve($doc, User::where('email', $email)->firstOrFail());
        $doc->refresh();
    }

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($sdaoA)])
        ->postJson("/api/documents/{$reference}/approve", []);

    $response->assertOk();
    $response->assertJson([
        'current_stage' => 'sdao_review',
        'activity_proposal' => ['approval' => ['approved_count' => 1, 'total_count' => 2]],
    ]);

    $doc->refresh();
    expect($doc->current_step_position)->toBe(4);
});

test('approve is blocked by a confirmed off-calendar venue conflict with a 422', function () {
    $doc = actionsTestSubmitOffCalendar($this, 'Shared Hall', '2026-12-10', '09:00', '11:00');
    actionsTestApprovedActivity($this->org, 'Shared Hall', '2026-12-10', '09:30', '10:30', 'Rival Event');

    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/approve", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('approve');

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
});

test('approve requires authentication', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $this->postJson("/api/documents/{$reference}/approve", [])->assertStatus(401);
});

test('a student cannot approve', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->student)])
        ->postJson("/api/documents/{$reference}/approve", [])
        ->assertStatus(403);
});

test('an approver who is not on the current step cannot approve', function () {
    $doc = actionsTestSubmitOnCalendar($this); // sits at step 1 (adviser)
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($dean)])
        ->postJson("/api/documents/{$reference}/approve", [])
        ->assertStatus(403);
});

test('a stale approve (already finalized by the other SDAO member) is a 403', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    foreach (['adviser-one@nu-lipa.edu.ph', 'chair-cs@nu-lipa.edu.ph', 'dean-ccit@nu-lipa.edu.ph', 'sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph', 'asst-director@nu-lipa.edu.ph', 'academic-director@nu-lipa.edu.ph', 'executive-director@nu-lipa.edu.ph'] as $email) {
        $this->engine->approve($doc, User::where('email', $email)->firstOrFail());
        $doc->refresh();
    }
    expect($doc->status)->toBe(DocumentStatus::Approved);

    // The adviser's own step is long finalized — approving again must 403,
    // not 500, since `review` (InReview-only) already denies it.
    $reference = ProposalReference::format($doc);
    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/approve", [])
        ->assertStatus(403);
});

// ── Request revision ─────────────────────────────────────────────────────────

test('request-revision requires remarks', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/request-revision", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('remarks');
});

test('request-revision saves the raw remarks and parses recognizable section labels', function () {
    Notification::fake();

    $doc = actionsTestSubmitOnCalendar($this);
    $this->engine->approve($doc, $this->adviser); // now at chair's step
    $doc->refresh();

    $remarks = "Please update the activity schedule and budget details.\n\nSections needing revision: Budget, Schedule & Venue";
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->chair)])
        ->postJson("/api/documents/{$reference}/request-revision", ['remarks' => $remarks]);

    $response->assertOk();
    $response->assertJson(['status' => 'revision_requested']);

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Returned->value)
        ->first();

    expect($transition->comment)->toBe($remarks);
    expect($transition->flagged_sections)->toEqualCanonicalizing(['budget', 'schedule_venue']);

    $officers = User::whereIn('email', [
        'student-alpha@students.nu-lipa.edu.ph',
        'student-delta@students.nu-lipa.edu.ph',
    ])->get();
    foreach ($officers as $officer) {
        Notification::assertSentTo($officer, DocumentOutcomeNotification::class);
    }
});

test('request-revision with the comma-label never flags Objectives by mistake', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $remarks = 'Please redo the request letter.'
        ."\n\nSections needing revision: Request Letter (must include Rationale, Objectives, and Program), Budget";

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/request-revision", ['remarks' => $remarks])
        ->assertOk();

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Returned->value)
        ->first();

    expect($transition->flagged_sections)->toContain('request_letter');
    expect($transition->flagged_sections)->toContain('budget');
    expect($transition->flagged_sections)->not->toContain('objectives');
});

test('request-revision ignores unknown labels but still saves the raw remarks', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);
    $remarks = 'Sections needing revision: Budget, Something Unrecognized, General';

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/request-revision", ['remarks' => $remarks])
        ->assertOk();

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Returned->value)
        ->first();

    expect($transition->comment)->toBe($remarks);
    expect($transition->flagged_sections)->toEqualCanonicalizing(['budget', 'general']);
});

// ── Reject ───────────────────────────────────────────────────────────────────

test('reject requires remarks', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('remarks');
});

test('reject terminates the document and saves the remarks', function () {
    Notification::fake();

    $doc = actionsTestSubmitOnCalendar($this);
    $reference = ProposalReference::format($doc);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($this->adviser)])
        ->postJson("/api/documents/{$reference}/reject", ['remarks' => 'Not aligned with academic objectives.']);

    $response->assertOk();
    $response->assertJson(['status' => 'rejected']);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Rejected);

    $transition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Rejected->value)
        ->first();
    expect($transition->comment)->toBe('Not aligned with academic objectives.');
});

test('a non-approver (never on this chain) gets 403 on reject', function () {
    $doc = actionsTestSubmitOnCalendar($this);
    $outsider = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $reference = ProposalReference::format($doc);

    $this->withHeaders(['Authorization' => 'Bearer '.actionsTestToken($outsider)])
        ->postJson("/api/documents/{$reference}/reject", ['remarks' => 'No.'])
        ->assertStatus(403);
});
