<?php

namespace App\Support;

/**
 * Single source of truth for the effective request upload ceiling, derived
 * from PHP's own `post_max_size` at runtime — so the friendly "files too
 * large" message always states the limit that is actually enforced, and never
 * drifts from php.ini.
 *
 * Used by the PostTooLargeException renderer in bootstrap/app.php to turn a
 * request that exceeds the limit into a clean in-app message instead of the
 * raw debug exception page.
 */
class UploadLimits
{
    /**
     * The effective `post_max_size` in whole megabytes (floored). Falls back
     * to a sane default if the directive is empty or set to 0 (unlimited).
     */
    public static function postMaxSizeMb(): int
    {
        $bytes = self::shorthandToBytes((string) ini_get('post_max_size'));

        if ($bytes <= 0) {
            return 100;
        }

        return (int) floor($bytes / 1024 ** 2);
    }

    /**
     * The user-facing message shown when a submission exceeds the request
     * size limit.
     */
    public static function tooLargeMessage(): string
    {
        return 'Your files are too large. Please reduce file sizes and try again (max total size: '
            .self::postMaxSizeMb().' MB).';
    }

    /**
     * Converts a PHP ini shorthand byte value ("100M", "1G", "512K", or a
     * plain byte count) into an integer number of bytes.
     */
    private static function shorthandToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower($value[strlen($value) - 1]);
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => (float) $value,
        };
    }
}
