<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Typed accessor for the global, admin-controlled "current academic period"
 * setting — the single source of truth for both the current term and the
 * current academic year (replaces the old, year-blind App\Support\CurrentTerm).
 * SDAO sets this system-wide via the settings screen; setting the term to 3rd
 * is what opens organization renewal season (App\Renewals\OpenRenewalSeason).
 *
 * Changing the current period never rewrites an already-stamped document's own
 * copy of the period it was submitted/approved under — each document keeps
 * its own snapshot, written once.
 */
class CurrentPeriod
{
    private const string KEY = 'current_period';

    private const string CACHE_KEY = 'current-academic-period';

    /**
     * Returns the current period. Caches the resolved, never-null canonical
     * string — deliberately not a `private static` in-process memo, since PHP
     * statics survive across Pest tests in the same process and would leak
     * state between tests despite RefreshDatabase. A shared cache store
     * (file/database/redis, whatever CACHE_STORE is) is cheap enough and is
     * correctly reset per test.
     *
     * The fallback-inside-the-callback is deliberate: Cache::rememberForever()
     * only re-runs its callback while the cached value is null, so a callback
     * that could itself return null (as the old CurrentTerm::get() did) would
     * re-query the database on every single request on any environment where
     * SettingsSeeder never ran — including every test. Resolving to a real
     * value up front avoids that.
     */
    public static function get(): AcademicPeriod
    {
        $value = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->where('key', self::KEY)->value('value')
                ?? AcademicPeriod::forDate(Date::now())->toString(),
        );

        return AcademicPeriod::tryFromString($value) ?? AcademicPeriod::forDate(Date::now());
    }

    /**
     * Sets the current period system-wide. Upserts — never creates a
     * duplicate row.
     */
    public static function set(AcademicPeriod $period): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $period->toString()]);
        Cache::forget(self::CACHE_KEY);
    }
}
