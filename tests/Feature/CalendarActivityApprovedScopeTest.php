<?php

use App\Enums\DocumentStatus;
use App\Models\CalendarActivity;
use Tests\Fixtures\CalendarFixtures;

/**
 * The authorization boundary for the centralized calendar and the public
 * landing page: CalendarActivity::published() returns only activities whose
 * Activity Proposal is fully Approved. An Activity Calendar's own status
 * never publishes anything. HomeControllerTest exercises the same guarantee
 * end-to-end; this pins the query itself.
 */
test('the published scope returns only activities with an Approved proposal', function () {
    foreach ([DocumentStatus::Draft, DocumentStatus::InReview, DocumentStatus::Returned, DocumentStatus::Rejected] as $status) {
        CalendarFixtures::proposalFor(CalendarFixtures::plannedActivity(), $status);
    }
    $published = CalendarFixtures::publishedActivity(['name' => 'Approved Event']);

    $results = CalendarActivity::published()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($published->id);
});

test('an approved Activity Calendar with no proposal publishes nothing', function () {
    CalendarFixtures::plannedActivity(DocumentStatus::Approved);

    expect(CalendarActivity::published()->get())->toHaveCount(0);
});

test('the published scope excludes every non-Approved proposal status, one at a time', function (DocumentStatus $status) {
    CalendarFixtures::proposalFor(CalendarFixtures::plannedActivity(), $status);
    CalendarFixtures::offCalendarProposal($status);

    expect(CalendarActivity::published()->get())->toHaveCount(0);
})->with([
    DocumentStatus::Draft,
    DocumentStatus::InReview,
    DocumentStatus::Returned,
    DocumentStatus::Rejected,
]);

test('an approved off-calendar proposal publishes its own row', function () {
    $activity = CalendarFixtures::offCalendarProposal(DocumentStatus::Approved);

    expect(CalendarActivity::published()->pluck('id')->all())->toBe([$activity->id]);
});
