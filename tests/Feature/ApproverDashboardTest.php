<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\ProposalCalendarMode;
use App\Enums\Role;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use App\Support\DisplayTimezone;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->shsCouncil = Organization::where('name', 'SHS Student Council')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentGamma = User::where('email', 'student-gamma@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail(); // Computing Society
    $this->adviserTwo = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail(); // IT Guild
    $this->adviserShs = User::where('email', 'adviser-shs@nu-lipa.edu.ph')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->chairIt = User::where('email', 'chair-it@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->principalShs = User::where('email', 'principal-shs@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    $this->academicDirector = User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();

    // Pin the period to "now" so a test that travels in time can never leave
    // a stale, differently-dated period in the cache.
    CurrentPeriod::set(AcademicPeriod::forDate(now()));
});

/** A calendar date $days from today in Asia/Manila — the zone the dashboard counts days in. */
function approverDashboardManilaDate(int $days): string
{
    return DisplayTimezone::convert(now())->addDays($days)->toDateString();
}

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

/** Loads the dashboard as $user and runs $assertions against the deferred approver props. */
function approverDashboardAs(User $user, Closure $assertions): void
{
    test()->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->loadDeferredProps('approver', $assertions));
}

test('the header greets by Manila time of day with the approver\'s role title, last name and scope', function () {
    $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(9, 0));

    $this->actingAs($this->deanCcit)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.greeting', 'Good morning')
            ->where('approverDashboard.lastName', 'CCIT')
            ->where('approverDashboard.roleTitle', 'Dean')
            ->where('approverDashboard.role', 'College Dean')
        );

    $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(14, 0));
    $this->actingAs($this->adviserOne)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.greeting', 'Good afternoon')
            ->where('approverDashboard.role', 'Adviser'));

    $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(20, 0));
    $this->actingAs($this->principalShs)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.greeting', 'Good evening')
            ->where('approverDashboard.role', 'Principal'));
});

test('each role gets its own scope pill, labelled by what it is scoped to', function (string $email, string $role, string $label, string $value) {
    $this->actingAs(User::where('email', $email)->firstOrFail())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.role', $role)
            ->where('approverDashboard.scope', ['label' => $label, 'value' => $value])
            ->has('approverDashboard.extraRoles', 0)
        );
})->with([
    'dean' => ['dean-ccit@nu-lipa.edu.ph', 'College Dean', 'School', 'School of Architecture, Computing, and Engineering'],
    'program chair' => ['chair-cs@nu-lipa.edu.ph', 'Program Chair', 'Program', 'BS Computer Science'],
    'principal' => ['principal-shs@nu-lipa.edu.ph', 'Principal', 'School', 'Senior High School'],
    'adviser' => ['adviser-one@nu-lipa.edu.ph', 'Adviser', 'Organization', 'Computing Society'],
]);

test('a role with no scope gets no scope pill at all', function (string $email, string $role) {
    $this->actingAs(User::where('email', $email)->firstOrFail())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.role', $role)
            ->where('approverDashboard.scope', null)
            ->has('approverDashboard.extraRoles', 0)
        );
})->with([
    'asst. director' => ['asst-director@nu-lipa.edu.ph', 'Asst. Director of Academic Services'],
    'academic director' => ['academic-director@nu-lipa.edu.ph', 'Academic Director'],
    'executive director' => ['executive-director@nu-lipa.edu.ph', 'Executive Director'],
]);

test('extra role pills appear only for another role assignment or an active officer seat', function () {
    $this->actingAs($this->adviserOne)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('approverDashboard.extraRoles', 0));

    // A second advised organization and an active officer seat each add one pill.
    RoleAssignment::create(['user_id' => $this->adviserOne->id, 'role' => Role::Adviser, 'organization_id' => $this->itGuild->id]);
    OrganizationMembership::create([
        'user_id' => $this->adviserOne->id,
        'organization_id' => $this->shsCouncil->id,
        'position' => OfficerPosition::Secretary->value,
        'academic_year' => AcademicYear::current(),
        'is_active' => true,
        'started_at' => now(),
    ]);

    $this->adviserOne->unsetRelations();
    $this->actingAs($this->adviserOne)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.scope', ['label' => 'Organization', 'value' => 'Computing Society'])
            ->where('approverDashboard.extraRoles', [
                ['label' => 'Adviser', 'value' => 'IT Guild'],
                ['label' => 'Secretary', 'value' => 'SHS Student Council'],
            ])
        );

    // A seat that has ended no longer shows.
    OrganizationMembership::where('user_id', $this->adviserOne->id)->update(['is_active' => false]);

    $this->actingAs($this->adviserOne)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('approverDashboard.extraRoles', 1));
});

test('the banner points at the waiting document with the soonest event date', function () {
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(20), 'Far Off');
    $soonest = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(3), 'Soonest');
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(9), 'In Between');

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.banner.count', 3)
        ->where('approverSummary.banner.document.id', $soonest->id)
        ->where('approverSummary.banner.document.title', $soonest->title)
        ->where('approverSummary.banner.document.daysUntilEvent', 3)
    );
});

test('a waiting document with no event date goes last, and the banner falls back to it when nothing has a date', function () {
    $dated = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(5), 'Dated');
    $undated = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(5), 'Undated');
    ActivityProposal::where('document_id', $undated->id)->update(['calendar_activity_id' => null]);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverNeedsReview.rows.0.id', $dated->id)
        ->where('approverNeedsReview.rows.1.id', $undated->id)
        ->where('approverNeedsReview.rows.1.eventDate', null)
        ->where('approverNeedsReview.rows.1.daysUntilEvent', null)
        ->where('approverSummary.banner.document.id', $dated->id)
    );

    ActivityProposal::where('document_id', $dated->id)->update(['calendar_activity_id' => null]);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.banner.document.daysUntilEvent', null)
        ->where('approverSummary.nextEvent', null)
    );
});

test('the next-event card shows the nearest waiting event within 7 days, and is empty when none is that close', function () {
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(9), 'Too Far');
    $withinWeek = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(6), 'Within A Week');
    $nearest = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(2), 'Nearest');

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.nextEvent.id', $nearest->id)
        ->where('approverSummary.nextEvent.organizationName', 'Computing Society')
        ->where('approverSummary.nextEvent.daysUntilEvent', 2)
    );

    $this->engine->approve($nearest, $this->adviserOne);
    $this->engine->approve($withinWeek, $this->adviserOne);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.waiting.count', 1)
        ->where('approverSummary.nextEvent', null)
    );
});

test('the waiting card counts only the form types this role can receive, and measures the oldest wait from the latest transition', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(5));
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Old');
    $this->travelTo($now);
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'New');

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.waiting.count', 2)
        ->where('approverSummary.waiting.oldestDays', 5)
        // An adviser only ever receives activity proposals — the four
        // short-chain form types are SDAO's and must not be listed.
        ->has('approverSummary.waiting.byType', 1)
        ->where('approverSummary.waiting.byType.0.formType', 'activity_proposal')
        ->where('approverSummary.waiting.byType.0.count', 2)
    );
});

test('"with you" days run from the latest transition, not from submission or updated_at', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(6));
    $doc = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Handed Up');

    $this->travelTo($now->copy()->subDays(2));
    $this->engine->approve($doc, $this->adviserOne);

    $this->travelTo($now);
    // Make updated_at lie: it must not be consulted.
    DB::table('documents')->where('id', $doc->id)->update(['updated_at' => $now->copy()->subDays(40)]);

    approverDashboardAs($this->chairCs, fn ($reload) => $reload
        ->where('approverNeedsReview.rows.0.id', $doc->id)
        ->where('approverNeedsReview.rows.0.daysWithYou', 2)
        ->where('approverSummary.waiting.oldestDays', 2)
    );

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverInProgress.0.id', $doc->id)
        ->where('approverInProgress.0.daysAtStep', 2)
    );
});

test('"with you" reads zero on the day a document arrives', function () {
    approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30));

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverNeedsReview.rows.0.daysWithYou', 0)
        ->where('approverSummary.waiting.oldestDays', 0)
    );
});

test('the reviewed card counts this approver\'s own decisions in the current term by outcome', function () {
    $now = now();

    $approved = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Approved One');
    $this->engine->approve($approved, $this->adviserOne);

    $approvedToo = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Approved Two');
    $this->engine->approve($approvedToo, $this->adviserOne);

    $returned = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Returned One');
    $this->engine->returnForRevision($returned, $this->adviserOne, 'Fix the budget.');

    $rejected = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Rejected One');
    $this->engine->reject($rejected, $this->adviserOne, 'Not allowed.');

    // A decision from a previous term never counts toward this one.
    $this->travelTo($now->copy()->subMonths(5));
    $old = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Old Term');
    $this->engine->approve($old, $this->adviserOne);
    $this->travelTo($now);

    // Another approver's decisions never count toward this one's.
    $this->engine->approve($approved, $this->chairCs);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverSummary.reviewed.approved', 2)
        ->where('approverSummary.reviewed.returned', 1)
        ->where('approverSummary.reviewed.rejected', 1)
        ->where('approverSummary.reviewed.total', 4)
    );
});

test('needs-your-review is capped at five rows but reports the full total, soonest event first', function () {
    $ids = [];

    foreach ([7, 3, 11, 1, 9, 5, 13] as $days) {
        $ids[$days] = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate($days), "In {$days}")->id;
    }

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverNeedsReview.total', 7)
        ->has('approverNeedsReview.rows', 5)
        ->where('approverNeedsReview.rows.0.id', $ids[1])
        ->where('approverNeedsReview.rows.1.id', $ids[3])
        ->where('approverNeedsReview.rows.4.id', $ids[9])
        ->where('approverNeedsReview.rows.0.formTypeLabel', 'Activity Proposal')
        ->where('approverNeedsReview.rows.0.organizationName', 'Computing Society')
    );
});

test('one approver never sees another approver\'s documents, and a document leaves the queue the moment it advances', function () {
    $mine = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Mine');
    $theirs = approverDashboardOnCalendarProposal($this->itGuild, $this->studentBeta, approverDashboardManilaDate(30), 'Theirs');
    $this->engine->approve($theirs, $this->adviserTwo);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverNeedsReview.total', 1)
        ->where('approverNeedsReview.rows.0.id', $mine->id)
        ->has('approverInProgress', 0)
        ->has('approverRecentDecisions', 0)
        ->where('approverSummary.reviewed.total', 0)
    );

    $this->engine->approve($mine, $this->adviserOne);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverNeedsReview.total', 0)
        ->has('approverInProgress', 1)
        ->where('approverInProgress.0.id', $mine->id)
    );

    // chair-cs gets Computing Society's document, never IT Guild's.
    approverDashboardAs($this->chairCs, fn ($reload) => $reload
        ->where('approverNeedsReview.total', 1)
        ->where('approverNeedsReview.rows.0.id', $mine->id)
        ->has('approverInProgress', 0)
    );
});

test('where-your-approved-documents-are-now shows the previous step, you and the current step on a regular chain', function () {
    $doc = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30));
    $this->engine->approve($doc, $this->adviserOne);
    $this->engine->approve($doc, $this->chairCs);

    // Regular chain: adviser -> chair -> dean. The chair sees Adviser (done), You (done), Dean (now), Done.
    approverDashboardAs($this->chairCs, fn ($reload) => $reload
        ->has('approverInProgress', 1)
        ->where('approverInProgress.0.steps', [
            ['label' => 'Adviser', 'state' => 'done'],
            ['label' => 'You', 'state' => 'done'],
            ['label' => 'Dean', 'state' => 'current'],
            ['label' => 'Done', 'state' => 'upcoming'],
        ])
        ->where('approverInProgress.0.trackerLabel', 'Approved by Adviser and you, now with Dean')
        ->where('approverInProgress.0.currentRole', 'Dean')
    );

    // The adviser is first in the chain, so the tracker starts at "You" and skips the chair.
    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->where('approverInProgress.0.steps', [
            ['label' => 'You', 'state' => 'done'],
            ['label' => 'Dean', 'state' => 'current'],
            ['label' => 'Done', 'state' => 'upcoming'],
        ])
        ->where('approverInProgress.0.trackerLabel', 'Approved by you, now with Dean')
    );
});

test('where-your-approved-documents-are-now reads the Senior High chain from its own template, by role', function () {
    $doc = approverDashboardOnCalendarProposal($this->shsCouncil, $this->studentGamma, approverDashboardManilaDate(30));
    $this->engine->approve($doc, $this->adviserShs);
    $this->engine->approve($doc, $this->principalShs);
    $this->engine->approve($doc, $this->sdaoA);

    // SHS chain: adviser -> principal -> SDAO. The principal sits at position 2 here,
    // not 3 as the dean does in a regular school, and SDAO is still waiting on its second member.
    approverDashboardAs($this->principalShs, fn ($reload) => $reload
        ->where('approverInProgress.0.steps', [
            ['label' => 'Adviser', 'state' => 'done'],
            ['label' => 'You', 'state' => 'done'],
            ['label' => 'SDAO', 'state' => 'current'],
            ['label' => 'Done', 'state' => 'upcoming'],
        ])
        ->where('approverInProgress.0.trackerLabel', 'Approved by Adviser and you, now with SDAO')
        ->where('approverInProgress.0.currentRole', 'SDAO')
    );
});

test('a document leaves where-your-approved-documents-are-now once it is fully approved, rejected or returned', function () {
    $finished = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Finished');
    $rejected = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Rejected Later');
    $returned = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Returned Later');
    $stillMoving = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Still Moving');

    foreach ([$finished, $rejected, $returned, $stillMoving] as $doc) {
        $this->engine->approve($doc, $this->adviserOne);
    }

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload->has('approverInProgress', 4));

    $this->engine->approve($finished, $this->chairCs);
    $this->engine->approve($finished, $this->deanCcit);
    $this->engine->approve($finished, $this->sdaoA);
    $this->engine->approve($finished, $this->sdaoB);
    $this->engine->approve($finished, $this->asstDirector);
    $this->engine->approve($finished, $this->academicDirector);
    $this->engine->approve($finished, $this->executiveDirector);
    $this->engine->reject($rejected, $this->chairCs, 'No.');
    $this->engine->returnForRevision($returned, $this->chairCs, 'Fix this.');

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->has('approverInProgress', 1)
        ->where('approverInProgress.0.id', $stillMoving->id)
    );
});

test('a returned-and-resubmitted document resumes at the returning approver, and stays in progress for the approvers below who already passed it', function () {
    $doc = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30));
    $this->engine->approve($doc, $this->adviserOne);
    $this->engine->returnForRevision($doc, $this->chairCs, 'Please revise.');
    $this->engine->resubmit($doc, $this->studentAlpha);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->has('approverInProgress', 1)
        ->where('approverInProgress.0.currentRole', 'Program Chair')
        ->where('approverNeedsReview.total', 0)
    );

    approverDashboardAs($this->chairCs, fn ($reload) => $reload
        ->where('approverNeedsReview.total', 1)
        ->has('approverInProgress', 0)
    );
});

test('an approved document stays in progress with a stalled flag from three days at the current step', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(4));
    $stalled = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Stalled');
    $this->engine->approve($stalled, $this->adviserOne);

    $this->travelTo($now->copy()->subDays(1));
    $fresh = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(30), 'Fresh');
    $this->engine->approve($fresh, $this->adviserOne);

    $this->travelTo($now);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->has('approverInProgress', 2)
        ->where('approverInProgress.0.id', $stalled->id)
        ->where('approverInProgress.0.daysAtStep', 4)
        ->where('approverInProgress.0.stalled', true)
        ->where('approverInProgress.1.id', $fresh->id)
        ->where('approverInProgress.1.daysAtStep', 1)
        ->where('approverInProgress.1.stalled', false)
    );
});

test('coming up lists approved activities in the next 14 days that this approver can view, soonest first', function () {
    $later = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(12), 'Later');
    driveProposalToApproved($later, $this->adviserOne, $this->chairCs, $this->deanCcit);

    $sooner = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(3), 'Sooner');
    driveProposalToApproved($sooner, $this->adviserOne, $this->chairCs, $this->deanCcit);

    $tooFar = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(20), 'Too Far');
    driveProposalToApproved($tooFar, $this->adviserOne, $this->chairCs, $this->deanCcit);

    $notMine = approverDashboardOnCalendarProposal($this->itGuild, $this->studentBeta, approverDashboardManilaDate(4), 'Not Mine');
    driveProposalToApproved($notMine, $this->adviserTwo, $this->chairIt, $this->deanCcit);

    $this->engine->approve(approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(5), 'Still In Review'), $this->adviserOne);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->has('approverComingUp', 2)
        ->where('approverComingUp.0.id', $sooner->id)
        ->where('approverComingUp.0.venue', 'Main Hall')
        ->where('approverComingUp.0.organizationName', 'Computing Society')
        ->where('approverComingUp.1.id', $later->id)
    );
});

test('recent decisions show the latest outcome per document with a relative date, and flag a returned document still waiting on the org', function () {
    $now = now();

    $this->travelTo($now->copy()->subDays(10));
    $old = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(60), 'Old Decision');
    $this->engine->approve($old, $this->adviserOne);

    $this->travelTo($now->copy()->subDays(5));
    $returned = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(60), 'Returned Decision');
    $this->engine->returnForRevision($returned, $this->adviserOne, 'Revise.');

    $this->travelTo($now->copy()->subDay());
    $resent = approverDashboardOnCalendarProposal($this->org, $this->studentAlpha, approverDashboardManilaDate(60), 'Resent Decision');
    $this->engine->returnForRevision($resent, $this->adviserOne, 'Revise.');
    $this->engine->resubmit($resent, $this->studentAlpha);

    $this->travelTo($now);

    approverDashboardAs($this->adviserOne, fn ($reload) => $reload
        ->has('approverRecentDecisions', 3)
        ->where('approverRecentDecisions.0.documentTitle', $resent->title)
        ->where('approverRecentDecisions.0.whenLabel', '1 day ago')
        ->where('approverRecentDecisions.0.waitingOnOrg', false)
        ->where('approverRecentDecisions.1.documentTitle', $returned->title)
        ->where('approverRecentDecisions.1.action', 'returned')
        ->where('approverRecentDecisions.1.whenLabel', '5 days ago')
        ->where('approverRecentDecisions.1.waitingOnOrg', true)
        ->where('approverRecentDecisions.2.action', 'approved')
        ->where('approverRecentDecisions.2.whenLabel', DisplayTimezone::convert($now->copy()->subDays(10))->format('M j'))
        ->where('approverRecentDecisions.2.waitingOnOrg', false)
    );
});

test('every card has an empty state for an approver with nothing waiting, in progress or decided', function () {
    approverDashboardAs($this->deanCcit, fn ($reload) => $reload
        ->where('approverSummary.banner.count', 0)
        ->where('approverSummary.banner.document', null)
        ->where('approverSummary.waiting.count', 0)
        ->where('approverSummary.waiting.oldestDays', null)
        ->where('approverSummary.nextEvent', null)
        ->where('approverSummary.reviewed.total', 0)
        ->where('approverNeedsReview.total', 0)
        ->has('approverNeedsReview.rows', 0)
        ->has('approverInProgress', 0)
        ->has('approverComingUp', 0)
        ->has('approverRecentDecisions', 0)
    );
});

test('the dashboard links to the existing proposal review queue, approved list and decision history', function () {
    $this->actingAs($this->adviserOne)->withoutVite()
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('approverDashboard.reviewHref', route('review.activity-proposals.index'))
            ->where('approverDashboard.trackHref', route('review.activity-proposals.index', ['filter' => 'approved']))
            ->where('approverDashboard.historyHref', route('review.activity-proposals.index', ['filter' => 'decided']))
        );
});

test('a student sees no approver dashboard data', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('approverDashboard', null));
});
