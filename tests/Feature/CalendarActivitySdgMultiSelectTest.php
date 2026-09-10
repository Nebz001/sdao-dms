<?php

use App\Enums\Sdg;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;

/**
 * Group B item 1 — CalendarActivity::$sdg is now a multi-select, cast via
 * AsEnumCollection::of(Sdg::class) onto a json column (mirrors
 * ActivityProposal::$partner_organizations' array shape). Coverage for the
 * cast round-trip itself; the FormRequest/HTTP-level coverage lives in
 * ActivityCalendarExactFieldsTest.
 */
function makeCalendarActivity(array $overrides = []): CalendarActivity
{
    $calendar = ActivityCalendar::factory()->for(Document::factory())->create();

    return CalendarActivity::factory()->for($calendar, 'calendar')->create($overrides);
}

test('multiple SDGs round-trip through the AsEnumCollection cast as Sdg enum instances', function () {
    $activity = makeCalendarActivity([
        'sdg' => [Sdg::QualityEducation->value, Sdg::ClimateAction->value],
    ]);

    $activity->refresh();

    expect($activity->sdg)->toHaveCount(2);
    expect($activity->sdg->first())->toBeInstanceOf(Sdg::class);
    expect($activity->sdg->map(fn (Sdg $s) => $s->value)->all())
        ->toBe([Sdg::QualityEducation->value, Sdg::ClimateAction->value]);
});

test('a single selected SDG still round-trips as a one-element collection', function () {
    $activity = makeCalendarActivity(['sdg' => [Sdg::NoPoverty->value]]);

    $activity->refresh();

    expect($activity->sdg)->toHaveCount(1);
    expect($activity->sdg->first())->toBe(Sdg::NoPoverty);
});

test('no SDG selected stores and reads back as null, not an empty collection', function () {
    $activity = makeCalendarActivity(['sdg' => null]);

    $activity->refresh();

    expect($activity->sdg)->toBeNull();
});
