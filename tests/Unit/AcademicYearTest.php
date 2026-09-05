<?php

use App\Support\AcademicYear;
use Carbon\Carbon;

// AcademicYear::current() now delegates to the stored CurrentPeriod setting
// (DB + cache), so it needs the framework booted — see
// tests/Feature/CurrentPeriodSettingTest.php for its coverage. forDate()
// stays a pure function of the clock and is tested here without booting
// Laravel at all.

test('a date in August starts the new academic year', function () {
    $date = Carbon::create(2026, 8, 1);
    expect(AcademicYear::forDate($date))->toBe('2026-2027');
});

test('a date in July still belongs to the previous academic year', function () {
    $date = Carbon::create(2026, 7, 31);
    expect(AcademicYear::forDate($date))->toBe('2025-2026');
});

test('a date in January belongs to the academic year that started the prior calendar year', function () {
    $date = Carbon::create(2027, 1, 15);
    expect(AcademicYear::forDate($date))->toBe('2026-2027');
});
