<?php

use App\Enums\DocumentStatus;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * End-to-end version of the authorization boundary pinned at the query
 * level in CalendarActivityApprovedScopeTest.php: a guest hitting the real
 * public route must only ever see Approved activities in the response.
 */
function seedHomeActivity(DocumentStatus $status, string $name): CalendarActivity
{
    $document = Document::factory()->create(['status' => $status]);
    $calendar = ActivityCalendar::factory()->for($document)->create();

    return CalendarActivity::factory()->for($calendar, 'calendar')->create([
        'name' => $name,
        'activity_date' => now()->addDays(5)->toDateString(),
    ]);
}

test('guests visiting home see only approved activities', function () {
    seedHomeActivity(DocumentStatus::Draft, 'Draft Event');
    seedHomeActivity(DocumentStatus::InReview, 'In Review Event');
    seedHomeActivity(DocumentStatus::Returned, 'Returned Event');
    seedHomeActivity(DocumentStatus::Rejected, 'Rejected Event');
    seedHomeActivity(DocumentStatus::Approved, 'Approved Event');

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->where('activities.0.name', 'Approved Event')
        );

    $response->assertDontSee('Draft Event');
    $response->assertDontSee('In Review Event');
    $response->assertDontSee('Returned Event');
    $response->assertDontSee('Rejected Event');
});

test('the public activities payload never includes document status or id', function () {
    seedHomeActivity(DocumentStatus::Approved, 'Approved Event');

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
    $document = Document::factory()->create(['status' => DocumentStatus::Approved]);
    $calendar = ActivityCalendar::factory()->for($document)->create();
    CalendarActivity::factory()->for($calendar, 'calendar')->create([
        'name' => 'Far Future Event',
        'activity_date' => now()->addDays(120)->toDateString(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->has('activities', 1)
            ->where('activities.0.name', 'Far Future Event')
        );
});

test('an approved activity in the past is still shown, so the calendar can navigate back to it', function () {
    $document = Document::factory()->create(['status' => DocumentStatus::Approved]);
    $calendar = ActivityCalendar::factory()->for($document)->create();
    CalendarActivity::factory()->for($calendar, 'calendar')->create([
        'name' => 'Past Event',
        'activity_date' => now()->subDays(120)->toDateString(),
    ]);

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
