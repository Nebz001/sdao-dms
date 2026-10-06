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
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * ActivityProposalReviewController::index()'s ?filter= modes — the "simple
 * query string filter" the approver dashboard's KPI cards link to. Complements
 * ProgramChairResolutionFailureTest, ReviewQueueAuthorizationTest, and
 * tests/Feature/Approval/DualSdaoApprovalTest.php, which cover the unfiltered
 * live queue's own ordering/authorization/logging behavior and must keep
 * passing unmodified.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
});

function reviewFilterOnCalendarProposal(Organization $org, User $officer): Document
{
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
        'name' => 'Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => now()->addDays(30)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $officer,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    app(SubmitActivityProposal::class)->execute(
        actor: $officer,
        document: $draft,
        objectives: "Overall Goal\n\nSpecific Objectives",
    );

    return $draft->fresh();
}

test('no filter returns the live queue, unchanged', function () {
    $doc = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filter', null)
            ->has('queue', 1)
            ->where('queue.0.id', $doc->id)
            ->where('queue.0.status', 'in_review')
            ->where('queue.0.decision', null)
        );
});

test('?filter=overdue narrows the live queue to documents waiting past the threshold', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(9));
    $overdue = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);

    $this->travelTo($now->copy()->subDays(5));
    $recent = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);

    $this->travelTo($now);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'overdue')
            ->has('queue', 1)
            ->where('queue.0.id', $overdue->id)
        );
});

test('?filter=approved and ?filter=returned switch to this approver\'s own decision history', function () {
    $approved = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->approve($approved, $this->adviserOne);

    $returned = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->returnForRevision($returned, $this->adviserOne, 'Fix this.');

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'approved']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'approved')
            ->has('queue', 1)
            ->where('queue.0.id', $approved->id)
            ->where('queue.0.decision.action', 'approved')
            ->where('queue.0.college', $this->org->school?->name)
            ->where('queue.0.title', 'Test Activity')
            // The document's REAL current status (now at the program chair's
            // step), not the hardcoded "in_review" the live queue always used.
            ->where('queue.0.status', 'in_review')
            ->where('queue.0.current_step_position', 2)
        );

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'returned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'returned')
            ->has('queue', 1)
            ->where('queue.0.id', $returned->id)
            ->where('queue.0.decision.action', 'returned')
            ->where('queue.0.status', 'returned')
        );
});

test('?filter=decided returns every decision this academic year, approved and returned together', function () {
    $approved = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->approve($approved, $this->adviserOne);

    $returned = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->returnForRevision($returned, $this->adviserOne, 'Fix this.');

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'decided']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filter', 'decided')
            ->has('queue', 2)
        );
});

test('an unrecognized filter value falls back to the live queue instead of erroring', function () {
    $doc = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'not-a-real-filter']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 1)
            ->where('queue.0.id', $doc->id)
        );
});

test('history filters are isolated between approvers', function () {
    $mine = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->approve($mine, $this->adviserOne);

    $theirs = reviewFilterOnCalendarProposal($this->itGuild, $this->studentBeta);
    $this->engine->approve($theirs, $this->adviserTwo);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'approved']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 1)
            ->where('queue.0.id', $mine->id)
        );

    $this->actingAs($this->adviserTwo)->withoutVite()
        ->get(route('review.activity-proposals.index', ['filter' => 'approved']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 1)
            ->where('queue.0.id', $theirs->id)
        );
});

test('the queue exposes step info, SLA tiers, tab counts and recent decisions for the stat cards', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(9));
    $overdue = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->travelTo($now);

    $approved = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->approve($approved, $this->adviserOne);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pending', 1)
            ->where('pending.0.id', $overdue->id)
            ->where('pending.0.tier', 'overdue')
            ->where('pending.0.waiting_days', 9)
            ->where('pending.0.step.position', 1)
            ->where('pending.0.step.name', 'Adviser review')
            ->where('pending.0.extra', $this->org->school?->name)
            ->where('pending.0.title', 'Test Activity')
            ->where('queue.0.title', 'Test Activity')
            ->where('queue.0.extra', $this->org->school?->name)
            ->where('tabCounts', ['pending' => 1, 'overdue' => 1, 'approved' => 1, 'returned' => 0])
            ->where('showTermStats', false)
            ->loadDeferredProps('queue-insights', fn ($reload) => $reload
                ->missing('stats')
                ->has('recent', 1)
                ->where('recent.0.result', 'approved')
                ->where('recent.0.college', $this->org->school?->name)
                ->where('recent.0.title', 'Test Activity')
            )
        );
});

test('a non-SDAO approver is never sent the submitted or decided this term data, and an SDAO member is', function () {
    $proposal = reviewFilterOnCalendarProposal($this->org, $this->studentAlpha);
    $this->engine->approve($proposal, $this->adviserOne);
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    foreach ([$this->adviserOne, User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail(), User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail()] as $approver) {
        $this->actingAs($approver)->withoutVite()
            ->get(route('review.activity-proposals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('showTermStats', false)
                ->missing('stats')
                ->loadDeferredProps('queue-insights', fn ($reload) => $reload
                    ->missing('stats')
                    ->has('recent')
                )
            );
    }

    $this->actingAs($sdao)->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showTermStats', true)
            ->loadDeferredProps('queue-insights', fn ($reload) => $reload
                ->has('stats.submitted')
                ->has('stats.decided')
            )
        );
});
