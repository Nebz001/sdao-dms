<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Log;

/**
 * The originally-reported live bug (fix plan, 2026_09_09_100000): a
 * school-affiliated org with no program (a data-integrity violation this same
 * plan's migration corrects) routes through the Regular proposal chain, whose
 * step 2 is ProgramChair. The ADVISER's own step-1 Approve click — a
 * perfectly legitimate action — silently 404s, because advancing to step 2
 * internally activates it (ApprovalEngine::approve():152), which resolves
 * ProgramChair for a null program_id and throws. Previously an uncaught,
 * unlogged ModelNotFoundException; now RoleDirectory::programChairFor()
 * throws a LogicException identifying the org, and
 * HandlesReviewActions::runReviewAction() catches it, logs it, and flashes a
 * user-facing error instead of a raw 404.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

/**
 * Builds a school-affiliated, no-program org (the bad shape) with a bound
 * adviser and an active president, then submits an on-calendar activity
 * proposal for it — all the way to InReview at step 1 (Adviser). Bypasses
 * registration entirely (SubmitOrganizationRegistration now refuses this
 * shape, per the same fix plan) since this test only needs the resulting org
 * state, not registration mechanics.
 */
function badShapeOrgWithSubmittedProposal(): array
{
    $school = School::where('type', 'regular')->firstOrFail();
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => null]);

    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value, 'organization_id' => $org->id]);

    $student = User::factory()->create();
    OrganizationMembership::factory()->president()->create(['user_id' => $student->id, 'organization_id' => $org->id]);

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
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Bad-Shape Org Event',
        'venue' => 'Test Hall',
        'activity_date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $document = app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        overallGoal: 'Overall Goal',
        specificObjectives: 'Specific Objectives',
    )['document'];

    expect($document->current_step_position)->toBe(1);

    return [$org, $adviser, $document];
}

test('HTTP: the adviser\'s own step-1 approve is caught, logged, and flashed as an error — not a raw 404', function () {
    Log::spy();
    [, $adviser, $document] = badShapeOrgWithSubmittedProposal();

    $response = $this->actingAs($adviser)
        ->post(route('review.activity-proposals.approve', $document));

    $response->assertRedirect(route('review.activity-proposals.index'));

    $this->actingAs($adviser)
        ->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.type', 'error')
            ->where('flash.toast.message', 'This document could not be processed — its next approver could not be determined. SDAO has been notified.')
        );

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Approval chain resolution failed')
        ->atLeast()->once();

    // The whole transaction rolled back — the step-1 approval itself must
    // not have silently partially applied.
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->current_step_position)->toBe(1);
});

test('the queue no longer silently hides a document at the broken step without a trace', function () {
    Log::spy();
    [, , $document] = badShapeOrgWithSubmittedProposal();

    // The real approve() flow can never actually leave a document sitting at
    // the broken step (the whole transaction rolls back — see the test
    // above), so this forces the document straight there to test the queue
    // filter's own logging in isolation, independent of how it got there.
    $document->update(['current_step_position' => 2]);

    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($dean)
        ->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('queue', []));

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Approver resolution failed while filtering queue')
        ->atLeast()->once();
});
