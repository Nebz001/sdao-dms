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
}
