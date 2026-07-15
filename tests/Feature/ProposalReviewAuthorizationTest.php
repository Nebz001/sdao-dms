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
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@sdao.test')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
});

function authApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'Auth Test Event',
        'venue' => 'Auth Hall',
        'activity_date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

function authSubmittedProposal(StartProposalDraft $start, SubmitActivityProposal $submit, User $student, Organization $org): Document
{
    $activity = authApprovedActivity($org);

    $draft = $start->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    return $submit->execute(
        actor: $student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    )['document'];
}

// ── Approver role gate ────────────────────────────────────────────────────────

test('correct step-1 approver (adviser) can approve at step 1', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    expect($doc->current_step_position)->toBe(1);

    // Should not throw
    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(2);
});

test('wrong-role approver (chair) at step 1 is refused', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    expect($doc->current_step_position)->toBe(1);

    expect(fn () => $this->engine->approve($doc, $this->chair))
        ->toThrow(UnauthorizedApproverException::class);
});

test('SDAO member cannot approve at step 1 (adviser step)', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    expect(fn () => $this->engine->approve($doc, $this->sdaoA))
        ->toThrow(UnauthorizedApproverException::class);
});

test('dean cannot approve at step 2 (chair step)', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(2);

    expect(fn () => $this->engine->approve($doc, $this->dean))
        ->toThrow(UnauthorizedApproverException::class);
});

// ── Membership gate ───────────────────────────────────────────────────────────

test('unaffiliated user cannot start a draft for the org', function () {
    $activity = authApprovedActivity($this->org);
    $unaffiliated = User::factory()->create();

    expect(fn () => $this->startDraft->execute(
        actor: $unaffiliated,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    ))->toThrow(AuthorizationException::class);
});

test('user affiliated with a different org cannot start a draft for this org', function () {
    $activity = authApprovedActivity($this->org);
    // student-beta is a member of IT Guild, not Computing Society
    $otherOrgStudent = User::where('email', 'student-beta@sdao.test')->firstOrFail();

    expect(fn () => $this->startDraft->execute(
        actor: $otherOrgStudent,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    ))->toThrow(AuthorizationException::class);
});

// ── Secretary can submit ──────────────────────────────────────────────────────

test('secretary can start a draft and submit a proposal for their org', function () {
    // student-delta is the secretary of Computing Society (seeded in MembershipSeeder)
    $activity = authApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->secretary,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    expect($draft->status)->toBe(DocumentStatus::Draft);
    expect($draft->submitted_by)->toBe($this->secretary->id);

    $result = $this->submitProposal->execute(
        actor: $this->secretary,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
});

// ── DocumentPolicy::review respects current step ─────────────────────────────

test('DocumentPolicy::review allows the current-step approver access', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    // Step 1 = adviser
    expect($this->adviser->can('review', $doc))->toBeTrue();
    expect($this->chair->can('review', $doc))->toBeFalse();
    expect($this->sdaoA->can('review', $doc))->toBeFalse();
});

test('DocumentPolicy::review updates when the document advances to the next step', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();

    // Step 2 = chair
    expect($this->chair->can('review', $doc))->toBeTrue();
    expect($this->adviser->can('review', $doc))->toBeFalse(); // no longer the current step
});

// ── HTTP: quorum-completing approve must not 403 (regression) ────────────────

test('HTTP: mid-chain approve (adviser, step 1) redirects back to the review show page', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    $this->actingAs($this->adviser)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));
});

test('HTTP: final approver (executive director) approving redirects to the queue, not a 403', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->student, $this->org);

    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $asstDirector = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $academicDirector = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $executiveDirector = User::where('email', 'executive-director@sdao.test')->firstOrFail();

    // Drive the chain to its last step (7): adviser, chair, dean, SDAO (both), asst director, academic director.
    foreach ([$this->adviser, $this->chair, $this->dean, $this->sdaoA, $sdaoB, $asstDirector, $academicDirector] as $approver) {
        $this->engine->approve($doc, $approver);
        $doc->refresh();
    }
    expect($doc->current_step_position)->toBe(7);

    $this->actingAs($executiveDirector)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.index'));

    // Following the redirect must succeed, not a 403 — the actual regression.
    $this->actingAs($executiveDirector)
        ->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk();

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
});
