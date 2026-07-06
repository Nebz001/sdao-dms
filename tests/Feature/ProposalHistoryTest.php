<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Calendar\VenueConflictChecker;
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
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);
    $this->checker = app(VenueConflictChecker::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
});

function historyApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'History Event',
        'venue' => 'History Hall',
        'activity_date' => '2026-11-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

// ── History records ───────────────────────────────────────────────────────────

test('transition history is written on submit', function () {
    $activity = historyApprovedActivity($this->org);

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

    $transitions = DocumentTransition::where('document_id', $doc->id)->get();
    expect($transitions)->not->toBeEmpty();

    $submitTransition = $transitions->first(
        fn ($t) => $t->action === TransitionAction::Submitted,
    );
    expect($submitTransition)->not->toBeNull();
    expect($submitTransition->to_status)->toBe(DocumentStatus::InReview);
    expect($submitTransition->actor_id)->toBe($this->student->id); // invariant #7
});

test('transition history records each approve with actor id', function () {
    $activity = historyApprovedActivity($this->org);

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

    // The engine writes Approved (actor recorded, step_position = current) then
    // Advanced (step advanced; step_position = next). Use the Approved row.
    $approveTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Approved->value)
        ->first();

    expect($approveTransition)->not->toBeNull();
    expect($approveTransition->actor_id)->toBe($this->adviser->id);
    expect($approveTransition->step_position)->toBe(1);
});

test('return for revision is recorded in history with comment', function () {
    $activity = historyApprovedActivity($this->org);

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

    $chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->engine->returnForRevision($doc, $chair, 'Incomplete budget breakdown.');
    $doc->refresh();

    $returnTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Returned->value)
        ->first();

    expect($returnTransition)->not->toBeNull();
    expect($returnTransition->comment)->toBe('Incomplete budget breakdown.');
    expect($returnTransition->actor_id)->toBe($chair->id);
    expect($returnTransition->step_position)->toBe(2); // chair is step 2
});

test('reject is recorded with final Rejected status in history', function () {
    $activity = historyApprovedActivity($this->org);

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

    $this->engine->reject($doc, $this->adviser, 'Not aligned with academic objectives.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);

    $rejectTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Rejected->value)
        ->first();

    expect($rejectTransition)->not->toBeNull();
    expect($rejectTransition->to_status)->toBe(DocumentStatus::Rejected);
    expect($rejectTransition->comment)->toBe('Not aligned with academic objectives.');
});

// ── Conflict-check endpoint (direct VenueConflictChecker calls) ───────────────

test('conflict checker returns confirmed when overlapping an Approved activity', function () {
    // Create an Approved activity for IT Guild
    $existingDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Existing Approved',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $this->itGuild->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $existingCal = ActivityCalendar::create([
        'document_id' => $existingDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    CalendarActivity::create([
        'activity_calendar_id' => $existingCal->id,
        'name' => 'Existing IT Guild Event',
        'venue' => 'Conference Room',
        'activity_date' => '2026-11-20',
        'start_time' => '10:00',
        'end_time' => '13:00',
    ]);

    $confirmed = $this->checker->confirmedConflicts('Conference Room', '2026-11-20', '11:00', '12:00');

    expect($confirmed)->not->toBeEmpty();
    expect($confirmed->first()->name)->toBe('Existing IT Guild Event');
});

test('conflict checker returns tentative when overlapping an InReview activity', function () {
    $inReviewDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Existing InReview',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $this->itGuild->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $inReviewCal = ActivityCalendar::create([
        'document_id' => $inReviewDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    CalendarActivity::create([
        'activity_calendar_id' => $inReviewCal->id,
        'name' => 'IT Guild InReview Event',
        'venue' => 'Lab B',
        'activity_date' => '2026-11-25',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    $confirmed = $this->checker->confirmedConflicts('Lab B', '2026-11-25', '09:30', '10:30');
    $tentative = $this->checker->tentativeConflicts('Lab B', '2026-11-25', '09:30', '10:30');

    expect($confirmed)->toBeEmpty();
    expect($tentative)->not->toBeEmpty();
    expect($tentative->first()->name)->toBe('IT Guild InReview Event');
});

test('conflict checker excludes self when excludeDocumentId is provided', function () {
    // Create an InReview proposal with its own off-cal activity
    $draft = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Self-referencing Activity',
            'venue' => 'Studio X',
            'activity_date' => '2026-12-10',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
        ],
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($doc->status)->toBe(DocumentStatus::InReview);

    // Tentative check without exclusion should see self as tentative
    $tentativeWithSelf = $this->checker->tentativeConflicts('Studio X', '2026-12-10', '09:00', '11:00');
    expect($tentativeWithSelf)->not->toBeEmpty();

    // With exclusion, self is invisible
    $tentativeExcluded = $this->checker->tentativeConflicts('Studio X', '2026-12-10', '09:00', '11:00', excludeDocumentId: $doc->id);
    expect($tentativeExcluded)->toBeEmpty();
});

// ── Page smoke tests (withoutVite) ────────────────────────────────────────────

test('proposal show page renders for the submitting student', function () {
    $activity = historyApprovedActivity($this->org);

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

    $this->actingAs($this->student)
        ->withoutVite()
        ->get("/activity-proposals/{$doc->id}")
        ->assertOk();
});

test('review show page renders for the current-step approver', function () {
    $activity = historyApprovedActivity($this->org);

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

    // Step 1 = adviser
    $this->actingAs($this->adviser)
        ->withoutVite()
        ->get("/review/activity-proposals/{$doc->id}")
        ->assertOk();
});

test('review show page returns 403 for a wrong-role user', function () {
    $activity = historyApprovedActivity($this->org);

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

    // Chair is step 2, not step 1 — should be 403
    $chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->actingAs($chair)
        ->withoutVite()
        ->get("/review/activity-proposals/{$doc->id}")
        ->assertForbidden();
});
