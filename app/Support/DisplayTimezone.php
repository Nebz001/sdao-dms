<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Timestamps are stored/cast in UTC (config/app.php has no APP_TIMEZONE
 * override); the office and every approver/student are Asia/Manila. Convert
 * through this one constant/helper anywhere a timestamp is displayed to a
 * person — printed forms (app/Printing/*) and outbound email alike — rather
 * than scattering timezone conversions or re-declaring the zone per class.
 */
class DisplayTimezone
{
    public const string ASIA_MANILA = 'Asia/Manila';

    public static function convert(CarbonInterface $date): CarbonInterface
    {
        return $date->setTimezone(self::ASIA_MANILA);
    }
}
