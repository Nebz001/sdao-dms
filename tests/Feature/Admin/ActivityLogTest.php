<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The full, filterable destination behind the admin dashboard's "View all
 * activity" link — AdminDashboardController::recentActivity() is capped to a
 * tight teaser (8 rows); this is where genuine browsing of
 * document_transitions happens. Sits behind the same `can:access-admin` gate
 * as the rest of admin/* — mirrors DocumentArchiveAuthorizationTest's cast of
 * non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * A directly-created transition — same precedent as DocumentArchiveTest's
 * archivedDocument() — for tests about the log's query/filter behavior, not
 * approval mechanics.
 */
function activityTransition(
    FormType $formType,
    Organization $org,
    User $actor,
    TransitionAction $action = TransitionAction::Submitted,
    string $title = 'Test Doc',
): DocumentTransition {
    $doc = Document::factory()->create([
        'form_type' => $formType,
        'organization_id' => $org->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'title' => $title,
    ]);

    return DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $actor->id,
        'action' => $action,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
}

test('an SDAO member can open the activity log', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk();
});

test('a student officer, an adviser, a dean, and a bare account all get 403 on the activity log', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
    $this->actingAs($this->deanCcit)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();

    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)->withoutVite()->get(route('admin.activity.index'))->assertForbidden();
});

test('the log lists a transition for every form type', function (FormType $formType) {
    activityTransition($formType, $this->org, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/activity/index')
            ->where('transitions.data.0.formTypeLabel', $formType->label())
            ->has('transitions.data', 1)
        );
})->with([
    'registration' => [FormType::OrganizationRegistration],
    'renewal' => [FormType::OrganizationRenewal],
    'activity calendar' => [FormType::ActivityCalendar],
    'activity proposal' => [FormType::ActivityProposal],
    'after-activity report' => [FormType::AfterActivityReport],
]);

test('the form_type filter narrows the result set', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'A Registration');
    activityTransition(FormType::OrganizationRenewal, $this->org, $this->studentAlpha, title: 'A Renewal');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => FormType::OrganizationRenewal->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.formTypeLabel', 'Organization Renewal')
        );
});

test('the action filter narrows the result set', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, TransitionAction::Submitted, 'Submitted Doc');
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->sdaoA, TransitionAction::Rejected, 'Rejected Doc');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['action' => TransitionAction::Rejected->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.action', 'rejected')
            ->where('transitions.data.0.action', 'rejected')
        );
});

test('search matches the document title or the organization name', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'Findable Title');
    activityTransition(FormType::OrganizationRegistration, $this->itGuild, $this->studentAlpha, title: 'Something Else');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 1));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['search' => 'IT Guild']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.organizationName', 'IT Guild')
        );
});

test('an unknown form_type or action filter value is ignored rather than emptying the page', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'Still Here');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => 'not_a_real_type', 'action' => 'also_bogus']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 1));
});

test('results are paginated at 20 per page, newest first', function () {
    foreach (range(1, 25) as $i) {
        $transition = activityTransition(FormType::ActivityCalendar, $this->org, $this->studentAlpha, title: "Doc {$i}");
        // Force distinct, increasing created_at so ordering is deterministic
        // rather than relying on same-second factory timestamps.
        $transition->forceFill(['created_at' => now()->addSeconds($i)])->save();
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 20)
            ->where('transitions.meta.last_page', 2)
            ->where('transitions.meta.total', 25)
            ->where('transitions.data.0.documentTitle', 'Doc 25')
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transitions.data', 5));
});

test('the table total follows the filters but the stat cards stay on the whole term', function () {
    activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'A');
    activityTransition(FormType::OrganizationRenewal, $this->org, $this->studentAlpha, title: 'B');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['form_type' => FormType::OrganizationRenewal->value, 'search' => 'nothing matches this']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('transitions.meta.total', 0)
            ->where('stats.total.total', 2)
        );
});

/** Pins the current period, so "this term" does not depend on the wall clock. */
function pinFirstTerm2026(): void
{
    CurrentPeriod::set(AcademicPeriod::fromString('2026-2027:first_term'));
}

/** A transition at an exact time, on a fresh document. */
function eventAt(string $at, Organization $org, User $actor, TransitionAction $action = TransitionAction::Submitted, string $title = 'Event'): DocumentTransition
{
    $transition = activityTransition(FormType::ActivityCalendar, $org, $actor, $action, $title);
    $transition->forceFill(['created_at' => $at])->save();

    return $transition;
}

test('total events this term groups every action into submitted, approved, returned, or rejected', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');

    foreach ([
        TransitionAction::Submitted, TransitionAction::Submitted, TransitionAction::Resubmitted,
        TransitionAction::Approved, TransitionAction::Advanced, TransitionAction::Completed,
        TransitionAction::Returned,
        TransitionAction::Rejected, TransitionAction::Withdrawn,
    ] as $action) {
        eventAt('2026-09-10 08:00:00', $this->org, $this->sdaoA, $action);
    }
    // Last term: not counted.
    eventAt('2026-05-10 08:00:00', $this->org, $this->sdaoA);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.termLabel', '1st Term')
            ->where('stats.total', ['total' => 9, 'submitted' => 3, 'approved' => 3, 'returned' => 1, 'rejected' => 2])
        );
});

test('events this week buckets the last 8 Manila Monday-start weeks and counts this week', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00'); // Wednesday; the week began Monday 2026-09-14 (Manila)

    eventAt('2026-09-15 03:00:00', $this->org, $this->sdaoA);            // this week
    eventAt('2026-09-16 03:00:00', $this->org, $this->sdaoA);            // this week
    eventAt('2026-09-13 17:00:00', $this->org, $this->sdaoA);            // Sun 17:00 UTC = Mon 01:00 Manila: this week
    eventAt('2026-09-13 15:00:00', $this->org, $this->sdaoA);            // Sun 23:00 Manila: last week
    eventAt('2026-08-05 03:00:00', $this->org, $this->sdaoA);            // 6 weeks back, inside the term

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.perWeek.weeks', [0, 1, 0, 0, 0, 0, 1, 3])
            ->where('stats.perWeek.thisWeek', 3)
        );
});

/**
 * A document handed to a step at $handedAt whose next transition is at $nextAt
 * (null = still waiting). Returns the document.
 */
function stepWait(Organization $org, User $actor, ProposalVariant $variant, int $position, string $handedAt, ?string $nextAt, bool $inReview = true): Document
{
    $template = WorkflowTemplate::where('form_type', FormType::ActivityProposal)->where('variant', $variant)->firstOrFail();
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => $variant,
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
        'status' => $inReview ? DocumentStatus::InReview : DocumentStatus::Approved,
        'current_step_position' => $inReview ? $position : null,
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id, 'actor_id' => $actor->id,
        'action' => $position === 1 ? TransitionAction::Submitted : TransitionAction::Advanced,
        'from_status' => DocumentStatus::InReview, 'to_status' => DocumentStatus::InReview,
        'step_position' => $position, 'created_at' => $handedAt,
    ]);

    if ($nextAt !== null) {
        DocumentTransition::create([
            'document_id' => $doc->id, 'actor_id' => $actor->id,
            'action' => TransitionAction::Returned,
            'from_status' => DocumentStatus::InReview, 'to_status' => DocumentStatus::Returned,
            'step_position' => $position, 'created_at' => $nextAt,
        ]);
    }

    return $doc;
}

test('slowest approval step groups by role, averages finished waits only, and counts who is waiting now', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');
    $variant = ProposalVariant::RegularOnCalendar;

    // Adviser (position 1): 3 finished waits of 1 day each.
    foreach (range(1, 3) as $i) {
        stepWait($this->org, $this->sdaoA, $variant, 1, "2026-09-0{$i} 08:00:00", '2026-09-0'.($i + 1).' 08:00:00', inReview: false);
    }
    // Dean (position 3): finished waits of 2, 4 and 6 days = average 4.0.
    foreach ([3, 5, 7] as $endDay) {
        stepWait($this->org, $this->sdaoA, $variant, 3, '2026-09-01 08:00:00', "2026-09-0{$endDay} 08:00:00", inReview: false);
    }
    // Two dean waits still open: left out of the average, but they are waiting now.
    stepWait($this->org, $this->sdaoA, $variant, 3, '2026-09-10 08:00:00', null);
    stepWait($this->itGuild, $this->sdaoA, $variant, 3, '2026-09-11 08:00:00', null);

    $this->actingAs($this->sdaoA)->withoutVite()->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.slowestStep.role', 'dean')
            ->where('stats.slowestStep.label', 'Dean review')
            ->where('stats.slowestStep.avgDays', 4)
            ->where('stats.slowestStep.finishedWaits', 3)
            ->where('stats.slowestStep.waitingNow', 2)
        );
});

test('slowest approval step is empty without enough finished waits', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');

    // Only two finished waits for the dean: below the minimum.
    stepWait($this->org, $this->sdaoA, ProposalVariant::RegularOnCalendar, 3, '2026-09-01 08:00:00', '2026-09-03 08:00:00', inReview: false);
    stepWait($this->org, $this->sdaoA, ProposalVariant::RegularOnCalendar, 3, '2026-09-01 08:00:00', '2026-09-05 08:00:00', inReview: false);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.slowestStep', null));
});

test('most active org counts events this term and breaks a tie by name, then id', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');

    // IT Guild: 2 events (1 submitted). Computing Society: 2 events (2 submitted). Tie on events.
    eventAt('2026-09-10 08:00:00', $this->itGuild, $this->sdaoA, TransitionAction::Submitted);
    eventAt('2026-09-11 08:00:00', $this->itGuild, $this->sdaoA, TransitionAction::Approved);
    eventAt('2026-09-10 08:00:00', $this->org, $this->sdaoA, TransitionAction::Submitted);
    eventAt('2026-09-11 08:00:00', $this->org, $this->sdaoA, TransitionAction::Resubmitted);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.topOrganization.name', 'Computing Society')
            ->where('stats.topOrganization.events', 2)
            ->where('stats.topOrganization.submitted', 2)
        );

    // One more event for IT Guild breaks the tie by count.
    eventAt('2026-09-12 08:00:00', $this->itGuild, $this->sdaoA, TransitionAction::Returned);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.topOrganization.name', 'IT Guild')
            ->where('stats.topOrganization.events', 3)
            ->where('stats.topOrganization.submitted', 1)
        );
});

test('the stat cards are empty and calm with no events this term', function () {
    pinFirstTerm2026();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total.total', 0)
            ->where('stats.perWeek.thisWeek', 0)
            ->where('stats.slowestStep', null)
            ->where('stats.topOrganization', null)
        );
});

test('the Date filter defaults to this term and offers week, last 30 days, academic year and all time', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00'); // Wednesday

    eventAt('2026-09-15 03:00:00', $this->org, $this->sdaoA, title: 'This week');
    eventAt('2026-09-05 03:00:00', $this->org, $this->sdaoA, title: 'Within 30 days');
    eventAt('2026-08-10 03:00:00', $this->org, $this->sdaoA, title: 'This term only');
    eventAt('2026-05-10 03:00:00', $this->org, $this->sdaoA, title: 'Last term, last academic year');
    eventAt('2026-09-30 03:00:00', $this->org, $this->sdaoA, title: 'Next week');
    $totalFor = fn (array $query) => $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', $query))
        ->viewData('page')['props']['transitions']['meta']['total'];

    expect($totalFor([]))->toBe(4)                       // this term (default), incl. one later in the term
        ->and($totalFor(['date' => 'term']))->toBe(4)
        ->and($totalFor(['date' => 'week']))->toBe(2)    // this week; the 09-30 event is in the future of "now" but still after the week start
        ->and($totalFor(['date' => 'last_30']))->toBe(3)
        ->and($totalFor(['date' => 'academic_year']))->toBe(4)
        ->and($totalFor(['date' => 'all']))->toBe(5)
        ->and($totalFor(['date' => 'bogus']))->toBe(4);  // unknown value falls back to the default
});

test('an explicit from/to range wins over the Date filter', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');
    eventAt('2026-05-10 03:00:00', $this->org, $this->sdaoA);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['from' => '2026-05-01', 'to' => '2026-05-31']))
        ->assertInertia(fn ($page) => $page->where('transitions.meta.total', 1));
});

test('search matches the name of the person who did it', function () {
    pinFirstTerm2026();
    activityTransition(FormType::ActivityCalendar, $this->org, $this->studentAlpha, title: 'By the student');
    activityTransition(FormType::ActivityCalendar, $this->org, $this->sdaoA, title: 'By SDAO');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index', ['search' => $this->studentAlpha->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transitions.data', 1)
            ->where('transitions.data.0.actorName', $this->studentAlpha->name)
        );
});

test('when is shown in Asia/Manila, date and time separately', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');
    eventAt('2026-09-11 07:42:00', $this->org, $this->sdaoA); // 15:42 in Manila

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transitions.data.0.whenDate', '9/11/2026')
            ->where('transitions.data.0.whenTime', '3:42 PM')
        );

    // Late UTC evening rolls into the next Manila day.
    eventAt('2026-09-11 17:30:00', $this->org, $this->sdaoA);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transitions.data.0.whenDate', '9/12/2026')
            ->where('transitions.data.0.whenTime', '1:30 AM')
        );
});

test('rows carry the college and the cleaned title', function () {
    pinFirstTerm2026();
    $registration = activityTransition(FormType::OrganizationRegistration, $this->org, $this->studentAlpha, title: 'Organization Registration — Computing Society (2026-2027)');
    $this->itGuild->forceFill(['school_id' => null, 'program_id' => null])->save();
    $noCollege = activityTransition(FormType::ActivityCalendar, $this->itGuild, $this->studentAlpha, title: 'Activity Calendar — IT Guild (1st Term 2026-2027)');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transitions.data', function ($rows) use ($registration, $noCollege) {
                $rows = collect($rows)->keyBy('id');

                return $rows[$registration->id]['documentTitle'] === 'Computing Society'
                    && $rows[$registration->id]['college'] !== 'No college'
                    && $rows[$noCollege->id]['college'] === 'No college'
                    && ! str_contains($rows[$noCollege->id]['documentTitle'], 'Activity Calendar —');
            })
        );
});

test('the document link is only given when the admin can open the document', function () {
    pinFirstTerm2026();
    $this->travelTo('2026-09-16 12:00:00');

    // Decided: SDAO can read it as an archive record.
    $approved = stepWait($this->org, $this->sdaoA, ProposalVariant::RegularOnCalendar, 1, '2026-09-01 08:00:00', '2026-09-02 08:00:00', inReview: false);
    // Still waiting on the dean: not SDAO's to open.
    $atDean = stepWait($this->org, $this->studentAlpha, ProposalVariant::RegularOnCalendar, 3, '2026-09-03 08:00:00', null);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertInertia(fn ($page) => $page
            ->where('transitions.data', function ($rows) use ($approved, $atDean) {
                $rows = collect($rows);

                return $rows->contains(fn ($r) => $r['href'] === route('review.activity-proposals.show', $approved))
                    && $rows->contains(fn ($r) => $r['href'] === null)
                    && ! $rows->contains(fn ($r) => $r['href'] === route('review.activity-proposals.show', $atDean));
            })
        );
});
