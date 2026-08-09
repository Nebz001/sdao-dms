<?php

use App\Enums\DocumentStatus;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;

/**
 * The authorization boundary for the public landing page's activities
 * widget: CalendarActivity::approved() must return activities for
 * Approved documents only, regardless of how many other statuses exist
 * alongside them. This is the direct test of that boundary — the request-
 * level test in HomeControllerTest.php exercises the same guarantee
 * end-to-end, but this one pins the query itself.
 */
function seedActivityWithStatus(DocumentStatus $status, string $name): CalendarActivity
{
    $document = Document::factory()->create(['status' => $status]);
    $calendar = ActivityCalendar::factory()->for($document)->create();

    return CalendarActivity::factory()->for($calendar, 'calendar')->create([
        'name' => $name,
        'activity_date' => now()->addDays(5)->toDateString(),
    ]);
}

test('the approved scope returns only activities whose document is Approved', function () {
    seedActivityWithStatus(DocumentStatus::Draft, 'Draft Event');
    seedActivityWithStatus(DocumentStatus::InReview, 'In Review Event');
    seedActivityWithStatus(DocumentStatus::Returned, 'Returned Event');
    seedActivityWithStatus(DocumentStatus::Rejected, 'Rejected Event');
    $approved = seedActivityWithStatus(DocumentStatus::Approved, 'Approved Event');

    $results = CalendarActivity::approved()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($approved->id);
    expect($results->first()->name)->toBe('Approved Event');
});

test('the approved scope excludes every non-Approved status, one at a time', function (DocumentStatus $status) {
    seedActivityWithStatus($status, "{$status->value} Event");

    expect(CalendarActivity::approved()->get())->toHaveCount(0);
})->with([
    DocumentStatus::Draft,
    DocumentStatus::InReview,
    DocumentStatus::Returned,
    DocumentStatus::Rejected,
]);
