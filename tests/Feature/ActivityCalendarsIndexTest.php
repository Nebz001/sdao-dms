<?php

use App\Calendar\SubmitActivityCalendar;
use App\Enums\Term;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->studentDelta = User::where('email', 'student-delta@sdao.test')->firstOrFail(); // secretary, Computing Society
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // president, IT Guild
});

function submitActivityCalendarFor(User $actor, Organization $org): void
{
    app(SubmitActivityCalendar::class)->execute(
        actor: $actor,
        organization: $org,
        term: Term::FirstTerm,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );
}

test('officer sees their org activity calendar in the index', function () {
    submitActivityCalendarFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/index')
            ->has('calendars', 1)
            ->where('calendars.0.organization.name', 'Computing Society')
        );
});

test('both president and secretary of the same org see the same calendar', function () {
    submitActivityCalendarFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentDelta)
        ->withoutVite()
        ->get(route('activity-calendars.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/index')
            ->has('calendars', 1)
        );
});

test('an org officer does not see another org\'s activity calendar', function () {
    submitActivityCalendarFor($this->studentAlpha, $this->computingSociety);

    $this->actingAs($this->studentBeta)
        ->withoutVite()
        ->get(route('activity-calendars.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/index')
            ->has('calendars', 0)
        );
});
