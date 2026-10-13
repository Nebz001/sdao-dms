<?php

use App\Enums\DocumentStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\CalendarFixtures;

/**
 * End-to-end version of the authorization boundary pinned at the query
 * level in CalendarActivityApprovedScopeTest.php: a guest hitting the real
 * public route must only ever see activities of fully Approved proposals.
 */
test('guests visiting home see only activities of approved proposals', function () {
    foreach ([DocumentStatus::Draft, DocumentStatus::InReview, DocumentStatus::Returned, DocumentStatus::Rejected] as $status) {
        CalendarFixtures::offCalendarProposal($status, ['name' => "{$status->value} proposal event", 'activity_date' => now()->addDays(5)->toDateString()]);
    }
    CalendarFixtures::plannedActivity(DocumentStatus::Approved, ['name' => 'Planned only event', 'activity_date' => now()->addDays(5)->toDateString()]);
    CalendarFixtures::publishedActivity(['name' => 'Approved Event', 'activity_date' => now()->addDays(5)->toDateString()]);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->where('activities.0.name', 'Approved Event')
        );

    $response->assertDontSee('draft proposal event');
    $response->assertDontSee('in_review proposal event');
    $response->assertDontSee('returned proposal event');
    $response->assertDontSee('rejected proposal event');
    $response->assertDontSee('Planned only event');
});

test('approving an Activity Calendar puts nothing on the public endpoint', function () {
    CalendarFixtures::plannedActivity(DocumentStatus::Approved, ['name' => 'Planned Event']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activities', 0));
});

test('an approved off-calendar proposal appears on the public endpoint', function () {
    CalendarFixtures::offCalendarProposal(DocumentStatus::Approved, ['name' => 'Off Calendar Event', 'activity_date' => now()->addDays(3)->toDateString()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activities', 1)->where('activities.0.name', 'Off Calendar Event'));
});

test('the public activities payload never includes document status or id', function () {
    CalendarFixtures::publishedActivity(['name' => 'Approved Event']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->has('activities.0', fn (Assert $activity) => $activity
                ->hasAll(['id', 'name', 'venue', 'activity_date', 'start_time', 'end_time', 'organization'])
                ->missing('status')
                ->missing('document_id')
                ->missing('description')
            )
        );
});

test('an approved activity far beyond 90 days in the future is still shown', function () {
    CalendarFixtures::publishedActivity(['name' => 'Far Future Event', 'activity_date' => now()->addDays(120)->toDateString()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->where('activities.0.name', 'Far Future Event')
        );
});

test('an approved activity in the past is still shown, so the calendar can navigate back to it', function () {
    CalendarFixtures::publishedActivity(['name' => 'Past Event', 'activity_date' => now()->subDays(120)->toDateString()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->where('activities.0.name', 'Past Event')
        );
});

test('authenticated users visiting home are redirected before any activities query runs', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('dashboard'));
});
