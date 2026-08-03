<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Minimal helper for academic-year strings (e.g. "2026-2027").
 *
 * School year at NU Lipa rolls over in August. A date before August belongs
 * to the academic year that started the previous calendar year.
 */
class AcademicYear
{
    /** Month (1-12) when the new academic year begins. Configurable via app config. */
    private const int ROLLOVER_MONTH = 8;

    /**
     * Returns the current academic year as a string, e.g. "2026-2027".
     */
    public static function current(): string
    {
        return self::forDate(Date::now());
    }

    /**
     * Returns the academic year for the given date.
     */
    public static function forDate(CarbonInterface $date): string
    {
        $startYear = $date->month >= self::ROLLOVER_MONTH
            ? $date->year
            : $date->year - 1;

        return "{$startYear}-".($startYear + 1);
    }

    /**
     * Returns [start, end) for the current academic year — a half-open date
     * range dashboard queries can scope `created_at` against. Documents
     * themselves have no `academic_year` column; this exists so "this
     * academic year" is computed once, from the same rollover rule as
     * current()/forDate(), rather than re-deriving August 1st elsewhere.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function currentRange(): array
    {
        $now = Date::now();
        $startYear = $now->month >= self::ROLLOVER_MONTH ? $now->year : $now->year - 1;

        return [
            Date::create($startYear, self::ROLLOVER_MONTH, 1)->startOfDay(),
            Date::create($startYear + 1, self::ROLLOVER_MONTH, 1)->startOfDay(),
        ];
    }
}
