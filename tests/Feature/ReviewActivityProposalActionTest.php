<?php

use App\ActivityProposals\Exceptions\ProposalVenueConflictException;
use App\ActivityProposals\ReviewActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
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
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Coverage for the shared review action (App\ActivityProposals\
 * ReviewActivityProposal) in isolation, BEFORE it is wired into the web
 * controller. Confirms it drives the real ApprovalEngine (quorum,
 * transitions, notifications) and reproduces the web's own off-calendar
 * venue-conflict re-check, so swapping the web controller onto it in the
 * next commit is a pure delegation change.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->action = app(ReviewActivityProposal::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
});

function approvedCalendarActivity(Organization $org, string $venue, string $date, string $start, string $end, string $name = 'Existing Approved Event'): CalendarActivity
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

function submitOnCalendarProposal($test): Document
{
    $activity = approvedCalendarActivity($test->org, 'Auditorium', '2026-11-15', '09:00', '11:00');

    $draft = $test->startDraft->execute(
        actor: $test->student,
        organization: $test->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    ['document' => $doc] = $test->submitProposal->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    return $doc;
}

function submitOffCalendarProposal($test, string $venue, string $date, string $start, string $end): Document
{
    $draft = $test->startDraft->execute(
        actor: $test->student,
        organization: $test->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Off-Calendar Proposal',
            'venue' => $venue,
            'activity_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'term' => 'first_term',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    ['document' => $doc] = $test->submitProposal->execute(
        actor: $test->student,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    return $doc;
}

test('approve on an on-calendar proposal advances the document via the real engine', function () {
    $doc = submitOnCalendarProposal($this);

    $this->action->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(2);

    $approveTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Approved->value)
        ->first();

    expect($approveTransition)->not->toBeNull();
    expect($approveTransition->actor_id)->toBe($this->adviser->id);
});

test('approve on an off-calendar proposal with no conflict proceeds normally', function () {
    $doc = submitOffCalendarProposal($this, 'Studio X', '2026-12-10', '09:00', '11:00');

    $this->action->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(2);
});

test('approve throws ProposalVenueConflictException when a rival booking was approved since submission', function () {
    $doc = submitOffCalendarProposal($this, 'Shared Hall', '2026-12-10', '09:00', '11:00');

    // A rival proposal claims the exact same slot and gets approved AFTER
    // this document entered review — the race the re-check guards against.
    approvedCalendarActivity($this->org, 'Shared Hall', '2026-12-10', '09:30', '10:30', 'Rival Event');

    expect(fn () => $this->action->approve($doc, $this->adviser))
        ->toThrow(ProposalVenueConflictException::class);

    $doc->refresh();

    // Never partially applied: still exactly where it was.
    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);

    expect(
        DocumentTransition::where('document_id', $doc->id)
            ->where('action', TransitionAction::Approved->value)
            ->exists()
    )->toBeFalse();
});

test('the venue conflict exception carries the exact web message text', function () {
    $doc = submitOffCalendarProposal($this, 'Shared Hall', '2026-12-10', '09:00', '11:00');
    approvedCalendarActivity($this->org, 'Shared Hall', '2026-12-10', '09:30', '10:30', 'Rival Event');

    try {
        $this->action->approve($doc, $this->adviser);
        $this->fail('Expected ProposalVenueConflictException to be thrown.');
    } catch (ProposalVenueConflictException $e) {
        expect($e->getMessage())->toBe(
            'Cannot approve: "Rival Event" at Shared Hall now conflicts with an already-approved booking. Return the document to the submitter to resolve.'
        );
        expect($e->conflictingActivityName)->toBe('Rival Event');
        expect($e->venue)->toBe('Shared Hall');
    }
});

test('approve on an off-calendar proposal ignores a merely tentative (InReview) rival', function () {
    $doc = submitOffCalendarProposal($this, 'Room 5', '2026-12-10', '09:00', '11:00');

    // A second InReview proposal for the same slot — only Approved rivals
    // hard-block; a tentative one must not throw.
    submitOffCalendarProposal($this, 'Room 5', '2026-12-10', '09:30', '10:30');

    $this->action->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(2);
});

test('reject delegates to the engine with the given comment', function () {
    $doc = submitOnCalendarProposal($this);

    $this->action->reject($doc, $this->adviser, 'Not aligned with academic objectives.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);

    $rejectTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Rejected->value)
        ->first();

    expect($rejectTransition->comment)->toBe('Not aligned with academic objectives.');
    expect($rejectTransition->actor_id)->toBe($this->adviser->id);
});

test('returnForRevision delegates to the engine with comment and flagged sections', function () {
    $doc = submitOnCalendarProposal($this);

    $this->action->approve($doc, $this->adviser);
    $doc->refresh();

    $this->action->returnForRevision(
        $doc,
        $this->chair,
        'Please fix the budget section.',
        ['budget', 'schedule_venue'],
        ['budget' => 'Numbers do not add up.'],
    );
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);

    $returnTransition = DocumentTransition::where('document_id', $doc->id)
        ->where('action', TransitionAction::Returned->value)
        ->first();

    expect($returnTransition->comment)->toBe('Please fix the budget section.');
    expect($returnTransition->flagged_sections)->toBe(['budget', 'schedule_venue']);
    expect($returnTransition->section_comments)->toBe(['budget' => 'Numbers do not add up.']);
});

test('approve still throws the engine\'s own exception for a non-approver', function () {
    $doc = submitOnCalendarProposal($this);

    // Chair is step 2, not step 1 — not this document's current approver yet.
    expect(fn () => $this->action->approve($doc, $this->chair))
        ->toThrow(UnauthorizedApproverException::class);
});

test('approve on an already-terminal document still throws InvalidTransitionException', function () {
    $doc = submitOnCalendarProposal($this);

    $this->action->reject($doc, $this->adviser, 'Rejected.');
    $doc->refresh();

    expect(fn () => $this->action->approve($doc, $this->adviser))
        ->toThrow(InvalidTransitionException::class);
});

test('a flagged section with a blank note stores no null note', function () {
    $doc = submitOnCalendarProposal($this);
    $this->action->approve($doc, $this->adviser);

    $this->action->returnForRevision($doc->refresh(), $this->chair, 'Fix it.', ['budget', 'schedule_venue'], ['budget' => null, 'schedule_venue' => '  ']);

    $returned = DocumentTransition::where('document_id', $doc->id)->where('action', TransitionAction::Returned->value)->first();

    expect($returned->flagged_sections)->toBe(['budget', 'schedule_venue']);
    expect($returned->section_comments)->toBeNull();
});

test('the review page renders a return that was stored with a null section note', function () {
    // Production document 140 shape: {"activity_details": null}, written
    // before blank notes were dropped. It made the page 500 with a TypeError.
    $doc = submitOnCalendarProposal($this);
    $this->action->approve($doc, $this->adviser);
    $this->action->returnForRevision($doc->refresh(), $this->chair, 'revise', ['budget', 'schedule_venue'], ['schedule_venue' => 'Wrong date.']);

    DocumentTransition::where('document_id', $doc->id)->where('action', TransitionAction::Returned->value)
        ->update(['section_comments' => json_encode(['budget' => null, 'schedule_venue' => 'Wrong date.'])]);

    $this->actingAs($this->chair)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view.history.0.action', 'returned')
            ->where('view.history.0.section_notes', [['label' => 'Schedule & Venue', 'note' => 'Wrong date.']])
        );
});
