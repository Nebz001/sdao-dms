<?php

use App\Enums\DocumentStatus;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\CalendarFixtures;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->user = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'IT Guild')->firstOrFail();
});

function makeActivityFixture(string $venue, string $date, string $start, string $end, DocumentStatus $status): void
{
    CalendarFixtures::offCalendarProposal($status, [
        'name' => 'Fixture Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ], Organization::where('name', 'IT Guild')->firstOrFail());
}

function conflictCheck($test, string $start, string $end): TestResponse
{
    return $test->actingAs($test->user)->postJson(route('activity-calendars.conflict-check'), [
        'activities' => [[
            'venue' => 'Auditorium',
            'activity_date' => '2026-09-10',
            'start_time' => $start,
            'end_time' => $end,
        ]],
    ])->assertOk();
}

test('conflict-check endpoint returns confirmed conflict for an approved proposal', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved);

    conflictCheck($this, '14:00', '16:00')
        ->assertJsonPath('results.0.confirmed.0.name', 'Fixture Event')
        ->assertJsonPath('results.0.tentative', []);
});

test('conflict-check endpoint returns tentative conflict for an in-routing proposal', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::InReview);

    conflictCheck($this, '14:00', '16:00')
        ->assertJsonPath('results.0.tentative.0.name', 'Fixture Event')
        ->assertJsonPath('results.0.confirmed', []);
});

test('conflict-check endpoint returns no conflicts for non-overlapping activity', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved);

    conflictCheck($this, '15:00', '17:00') // touching endpoint: no conflict
        ->assertJsonPath('results.0.confirmed', [])
        ->assertJsonPath('results.0.tentative', []);
});

test('an approved Activity Calendar entry never hard-blocks, it only warns as planned', function () {
    CalendarFixtures::plannedActivity(DocumentStatus::Approved, [
        'name' => 'Planned Event', 'venue' => 'Auditorium', 'activity_date' => '2026-09-10',
        'start_time' => '13:00', 'end_time' => '15:00',
    ], $this->org);

    conflictCheck($this, '14:00', '16:00')
        ->assertJsonPath('results.0.confirmed', [])
        ->assertJsonPath('results.0.tentative.0.name', 'Planned Event');
});

test('a planned entry whose proposal is approved hard-blocks once and is not also reported as planned', function () {
    CalendarFixtures::publishedActivity([
        'name' => 'Published Event', 'venue' => 'Auditorium', 'activity_date' => '2026-09-10',
        'start_time' => '13:00', 'end_time' => '15:00',
    ], $this->org);

    conflictCheck($this, '14:00', '16:00')
        ->assertJsonPath('results.0.confirmed.0.name', 'Published Event')
        ->assertJsonPath('results.0.tentative', []);
});

test('returned and rejected proposals and in-review calendars produce no conflict', function () {
    $slot = ['venue' => 'Auditorium', 'activity_date' => '2026-09-10', 'start_time' => '13:00', 'end_time' => '15:00'];
    CalendarFixtures::offCalendarProposal(DocumentStatus::Returned, $slot, $this->org);
    CalendarFixtures::offCalendarProposal(DocumentStatus::Rejected, $slot, $this->org);
    CalendarFixtures::plannedActivity(DocumentStatus::InReview, $slot, $this->org);

    conflictCheck($this, '14:00', '16:00')
        ->assertJsonPath('results.0.confirmed', [])
        ->assertJsonPath('results.0.tentative', []);
});
