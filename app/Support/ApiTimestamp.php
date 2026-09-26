<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The mobile contract's timestamp format: UTC, milliseconds (not
 * microseconds), e.g. "2026-08-18T08:30:00.000Z". Computed in PHP from
 * whatever timezone the given Carbon instance is already in — never a SQL
 * date function, so this behaves identically on SQLite (tests) and
 * Postgres (dev/production).
 */
class ApiTimestamp
{
    public static function format(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date->clone()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
