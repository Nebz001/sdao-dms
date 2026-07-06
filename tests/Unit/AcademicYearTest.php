<?php

use App\Support\AcademicYear;
use Carbon\Carbon;

test('AcademicYear::current() returns a year-range string', function () {
    $result = AcademicYear::current();
    expect($result)->toMatch('/^\d{4}-\d{4}$/');
});

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
