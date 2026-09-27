<?php

use App\Support\ApiTimestamp;
use Illuminate\Support\Carbon;

test('formats a UTC instant with millisecond precision', function () {
    $date = Carbon::create(2026, 8, 18, 8, 30, 0, 'UTC')->addMilliseconds(0);

    expect(ApiTimestamp::format($date))->toBe('2026-08-18T08:30:00.000Z');
});

test('converts a non-UTC instant to UTC first', function () {
    // 09:00 in Asia/Manila (UTC+8) is 01:00 UTC.
    $date = Carbon::create(2026, 10, 5, 9, 0, 0, 'Asia/Manila');

    expect(ApiTimestamp::format($date))->toBe('2026-10-05T01:00:00.000Z');
});

test('keeps milliseconds, not microseconds', function () {
    $date = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC')->addMicroseconds(123456);

    expect(ApiTimestamp::format($date))->toBe('2026-01-01T00:00:00.123Z');
});

test('null in, null out', function () {
    expect(ApiTimestamp::format(null))->toBeNull();
});

test('does not mutate the original Carbon instance', function () {
    $date = Carbon::create(2026, 10, 5, 9, 0, 0, 'Asia/Manila');
    $originalTimezone = $date->getTimezone()->getName();

    ApiTimestamp::format($date);

    expect($date->getTimezone()->getName())->toBe($originalTimezone);
});
