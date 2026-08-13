<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Hardening (NOT a fix for an independent leak): ActivityCalendarController /
 * ActivityProposalController conflictCheck() return other orgs' activity
 * title + org name on a conflict, but investigation confirmed this is a
 * strict subset of what CalendarController::index() (the shared /calendar
 * page, invariant #6) already renders to the same ['auth','verified']
 * audience by design — see ConflictCheckEndpointTest.php, which deliberately
 * pins the cross-org `.name` disclosure as intended behavior. The only
 * conflictCheck()-specific delta is how efficiently it can be probed: a
 * scriptable POST endpoint with an unbounded batch and no rate limit.
 *
 * This file pins the two closed levers — a rate limit shared with
 * adviserSearch()'s throttle:30,1, and a batch cap on the calendar variant's
 * `activities` array — while leaving the response shape untouched.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->user = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

function validConflictCheckActivity(int $i = 0): array
{
    return [
        'venue' => "Rate Limit Test Venue {$i}",
        'activity_date' => '2026-09-10',
        'start_time' => '14:00',
        'end_time' => '16:00',
    ];
}

test('calendar conflict-check rejects a batch larger than the cap, not silently truncated', function () {
    $activities = array_map(fn ($i) => validConflictCheckActivity($i), range(1, 21));

    $response = $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), ['activities' => $activities]);

    $response->assertInvalid(['activities']);
});

test('calendar conflict-check accepts a batch at the cap', function () {
    $activities = array_map(fn ($i) => validConflictCheckActivity($i), range(1, 20));

    $response = $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), ['activities' => $activities]);

    $response->assertOk();
});

test('calendar conflict-check is rate limited, but a real conflict is still detected within the limit', function () {
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Rate Limit Fixture Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);
    CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Rate Limit Fixture Event',
        'venue' => 'Shared Auditorium',
        'activity_date' => '2026-09-10',
        'start_time' => '13:00',
        'end_time' => '15:00',
    ]);

    // 30 requests within throttle:30,1 all succeed — proves the limit doesn't
    // break the legitimate debounced-typing use case. The 30th is a real
    // conflict-matching slot: the conflict is still detected correctly right
    // up against the ceiling.
    for ($i = 1; $i < 30; $i++) {
        $this->actingAs($this->user)
            ->postJson(route('activity-calendars.conflict-check'), ['activities' => [validConflictCheckActivity($i)]])
            ->assertOk();
    }

    $conflictResponse = $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), [
            'activities' => [[
                'venue' => 'Shared Auditorium',
                'activity_date' => '2026-09-10',
                'start_time' => '14:00',
                'end_time' => '16:00',
            ]],
        ]);
    $conflictResponse->assertOk();
    $conflictResponse->assertJsonPath('results.0.confirmed.0.name', 'Rate Limit Fixture Event');

    // The 31st request within the same minute is now over the ceiling.
    $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), ['activities' => [validConflictCheckActivity(999)]])
        ->assertTooManyRequests();
});

test('proposal conflict-check is rate limited the same way', function () {
    for ($i = 1; $i <= 30; $i++) {
        $this->actingAs($this->user)
            ->postJson(route('activity-proposals.conflict-check'), validConflictCheckActivity($i))
            ->assertOk();
    }

    $this->actingAs($this->user)
        ->postJson(route('activity-proposals.conflict-check'), validConflictCheckActivity(999))
        ->assertTooManyRequests();
});
