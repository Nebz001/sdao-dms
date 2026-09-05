<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Minimal helper for academic-year strings (e.g. "2026-2027").
 *
 * School year at NU Lipa rolls over in August. A date before August belongs
 * to the academic year that started the previous calendar year.
 */
class AcademicYear
{
    /** Month (1-12) when the new academic year begins. */
    private const int ROLLOVER_MONTH = 8;

    /**
     * Returns the current academic year as a string, e.g. "2026-2027".
     *
     * Delegates to the stored, admin-controlled current period
     * (App\Support\CurrentPeriod) rather than the wall clock — the academic
     * year can no longer drift silently on August 1st, and SDAO can correct
     * it directly. Signature is deliberately unchanged from before this
     * delegation existed, so every existing call site and test fixture that
     * reads `AcademicYear::current()` keeps working without modification.
     */
    public static function current(): string
    {
        return CurrentPeriod::get()->academicYear;
    }

    /**
     * Returns the academic year for the given date. Deliberately still a pure
     * function of the clock, unlike current() — this is what historical
     * documents and printed forms need: "what academic year was it when this
     * was created", not "what does the admin currently say the year is".
     */
    public static function forDate(CarbonInterface $date): string
    {
        $startYear = $date->month >= self::ROLLOVER_MONTH
            ? $date->year
            : $date->year - 1;

        return "{$startYear}-".($startYear + 1);
    }
}
