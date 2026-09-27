<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail(); // Computing Society
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail(); // IT Guild
    $this->chairCs = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->chairIt = User::where('email', 'chair-it@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    $this->academicDirector = User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * An on-calendar Activity Proposal, submitted by $officer for $org, whose
 * activity happens on $activityDate. Returns the Document fresh from the DB
 * (SubmitActivityProposal::execute() returns an array, not the model).
 */
function approverDashboardOnCalendarProposal(Organization $org, User $officer, string $activityDate, string $title = 'Test Activity'): Document
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
        'name' => $title,
        'venue' => 'Main Hall',
        'activity_date' => $activityDate,
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

/**
 * An in-review Organization Registration — SDAO's own single-step short
 * chain (WorkflowTemplateSeeder), never routed to any non-SDAO role. Used to
 * prove pendingByType() correctly zero-fills the four short-chain form types
 * for a non-SDAO approver.
 */
function approverDashboardInReviewRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $engine->submit($doc, $submitter);

    return $doc->refresh();
}

/**
 * Drives a Regular-school on-calendar proposal all the way to Approved:
 * adviser -> chair -> dean -> SDAO (both) -> asst. director -> academic
 * director -> executive director (WorkflowTemplateSeeder's chain order).
 */
function driveProposalToApproved(Document $document, User $adviser, User $chair, User $dean): void
{
    $engine = app(ApprovalEngine::class);
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    $academicDirector = User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail();
    $executiveDirector = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();

    $engine->approve($document, $adviser);
    $engine->approve($document, $chair);
    $engine->approve($document, $dean);
    $engine->approve($document, $sdaoA);
    $engine->approve($document, $sdaoB);
    $engine->approve($document, $asstDirector);
    $engine->approve($document, $academicDirector);
    $engine->approve($document, $executiveDirector);
}

test('waiting-on-you, overdue, and the priority queue reflect only documents currently at the adviser\'s step', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(5));
    $overdue = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Overdue Activity');

    $this->travelTo($now->copy()->subDay());
    $recent = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Recent Activity');

    $this->travelTo($now);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.waitingOnYou.count', 2)
                ->where('approverKpis.overdue.count', 1)
                ->has('approverQueue', 2)
                ->where('approverQueue.0.id', $overdue->id)
                ->where('approverQueue.0.waitTier', 'overdue')
                ->where('approverQueue.0.daysWaiting', 5)
                ->where('approverQueue.1.id', $recent->id)
                ->where('approverQueue.1.waitTier', 'normal')
                ->where('approverQueue.1.formTypeLabel', 'Activity Proposal')
            )
        );
});

test('approved/returned KPIs, outcome split, and average review time are computed from this approver\'s own decisions', function () {
    $now = now();

    // Submitted 10 days ago, approved 8 days ago -> 48h review time.
    $this->travelTo($now->copy()->subDays(10));
    $approved = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString());
    $this->travelTo($now->copy()->subDays(8));
    $this->engine->approve($approved, $this->adviserOne);

    // Submitted 3 days ago, returned 2 days ago -> 24h review time.
    $this->travelTo($now->copy()->subDays(3));
    $returned = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString());
    $this->travelTo($now->copy()->subDays(2));
    $this->engine->returnForRevision($returned, $this->adviserOne, 'Please fix the budget.');

    $this->travelTo($now);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.approved.count', 1)
                ->where('approverKpis.returned.count', 1)
                ->where('approverOutcomeSplit.approved', 1)
                ->where('approverOutcomeSplit.returned', 1)
                ->where('approverOutcomeSplit.rejected', 0)
                ->where('approverKpis.averageReviewTime.hours', 36)
                ->where('approverKpis.averageReviewTime.sampleSize', 2)
            )
        );
});

test('waiting time buckets the pending queue into normal/warning/overdue tiers, matching ApproverQueue\'s own thresholds', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(5));
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Overdue');

    $this->travelTo($now->copy()->subDays(2));
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Warning');

    $this->travelTo($now);
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Normal');

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->has('approverWaitingTime', 3)
                ->where('approverWaitingTime.0.tier', 'normal')
                ->where('approverWaitingTime.0.count', 1)
                ->where('approverWaitingTime.1.tier', 'warning')
                ->where('approverWaitingTime.1.count', 1)
                ->where('approverWaitingTime.2.tier', 'overdue')
                ->where('approverWaitingTime.2.count', 1)
            )
        );
});

test('the priority queue flags a document as resubmitted only when it came back to this approver after a return, and shows a real waiting-since date', function () {
    $fresh = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(30)->toDateString(), 'Fresh');

    $wasReturned = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(30)->toDateString(), 'Returned Then Resubmitted');
    $this->engine->returnForRevision($wasReturned, $this->adviserOne, 'Please fix this.');
    $this->engine->resubmit($wasReturned, $this->studentAlpha);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->has('approverQueue', 2)
                ->where('approverQueue.0.id', $fresh->id)
                ->where('approverQueue.0.wasResubmitted', false)
                ->whereType('approverQueue.0.waitingSince', 'string')
                ->where('approverQueue.1.id', $wasReturned->id)
                ->where('approverQueue.1.wasResubmitted', true)
            )
        );
});

test('review activity buckets this approver\'s decisions by week, excluding anything older than 8 weeks', function () {
    $now = now();

    $thisWeek = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'This Week');
    $this->engine->approve($thisWeek, $this->adviserOne);

    $this->travelTo($now->copy()->subWeeks(2));
    $twoWeeksAgo = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Two Weeks Ago');
    $this->engine->approve($twoWeeksAgo, $this->adviserOne);

    $this->travelTo($now->copy()->subWeeks(9));
    $tooOld = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $now->copy()->addDays(30)->toDateString(), 'Too Old');
    $this->engine->approve($tooOld, $this->adviserOne);

    $this->travelTo($now);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->has('approverReviewActivity', 8)
                ->where('approverReviewActivity.7.total', 1) // this week (last bucket)
                ->where('approverReviewActivity.5.total', 1) // 2 weeks ago
                ->where(
                    'approverReviewActivity',
                    fn ($weeks) => collect($weeks)->sum('total') === 2, // the 9-week-old decision never appears
                )
            )
        );
});

test('recent decisions lists only this approver\'s own Approved/Returned/Rejected actions, newest first, capped at 8', function () {
    $ids = [];

    for ($i = 0; $i < 9; $i++) {
        $this->travelTo(now()->addMinute());
        $doc = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(30)->toDateString(), "Proposal {$i}");
        $this->engine->returnForRevision($doc, $this->adviserOne, 'Revise this.');
        $ids[] = $doc->id;
    }

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->has('approverRecentDecisions', 8)
                ->where('approverRecentDecisions.0.id', end($ids))
                ->where('approverRecentDecisions.0.action', 'returned')
                ->where('approverRecentDecisions.0.formTypeLabel', 'Activity Proposal')
            )
        );
});

test('upcoming events only shows activities this specific approver helped approve, within the next 7 days', function () {
    $soon = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(3)->toDateString(), 'Soon Enough');
    driveProposalToApproved($soon, $this->adviserOne, $this->chairCs, $this->deanCcit);

    $tooFar = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(10)->toDateString(), 'Too Far Out');
    driveProposalToApproved($tooFar, $this->adviserOne, $this->chairCs, $this->deanCcit);

    $notMine = approverDashboardOnCalendarProposal($this->itGuild, $this->studentBeta, now()->addDays(3)->toDateString(), 'Not My Approval');
    driveProposalToApproved($notMine, $this->adviserTwo, $this->chairIt, $this->deanCcit);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->has('approverUpcomingEvents', 1)
                ->where('approverUpcomingEvents.0.id', $soon->id)
                ->where('approverUpcomingEvents.0.venue', 'Main Hall')
            )
        );
});

test('a decision from a previous academic year is excluded from this term\'s counts', function () {
    [$yearStart] = CurrentPeriod::get()->academicYearRange();
    $now = now();

    $this->travelTo($yearStart->copy()->subDay());
    $old = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, $yearStart->copy()->addDays(35)->toDateString());
    $this->engine->approve($old, $this->adviserOne);

    $this->travelTo($now);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.approved.count', 0)
                ->where('approverOutcomeSplit.approved', 0)
                // Recent Decisions is a true "last N actions" feed, deliberately
                // NOT term-scoped (same convention as AdminDashboardController's
                // own Recent Activity card) — the old decision still appears
                // there even though it's excluded from every term-scoped figure.
                ->has('approverRecentDecisions', 1)
            )
        );
});

test('one approver never sees another approver\'s pending queue or decisions, and a document leaves the queue the moment it advances', function () {
    $mine = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, now()->addDays(30)->toDateString());
    $theirs = approverDashboardOnCalendarProposal($this->itGuild, $this->studentBeta, now()->addDays(30)->toDateString());
    $this->engine->returnForRevision($theirs, $this->adviserTwo, 'Not yours.');

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.waitingOnYou.count', 1)
                ->where('approverQueue.0.id', $mine->id)
                ->where('approverKpis.returned.count', 0)
                ->has('approverRecentDecisions', 0)
            )
        );

    // adviser-one approves their own proposal — it moves to chair-cs and
    // must immediately drop out of adviser-one's own "waiting on you".
    $this->engine->approve($mine, $this->adviserOne);

    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.waitingOnYou.count', 0)
            )
        );

    $this->actingAs($this->chairCs)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.waitingOnYou.count', 1)
            )
        );

    // adviser-two, symmetrically, sees only their own IT Guild proposal.
    $this->actingAs($this->adviserTwo)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('approver', fn ($reload) => $reload
                ->where('approverKpis.waitingOnYou.count', 0) // theirs was returned, no longer at the adviser's step
                ->where('approverKpis.returned.count', 1)
            )
        );
});

test('a student sees no approver dashboard data', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('approverDashboard', null));
});
