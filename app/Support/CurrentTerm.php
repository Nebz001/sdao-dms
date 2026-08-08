<?php

namespace App\Support;

use App\Enums\Term;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed accessor for the global, admin-controlled "current term" setting
 * (Phase 2 item 6). SDAO/admin sets this system-wide via the settings screen;
 * new Activity Calendar submissions read it at creation time and stamp it on
 * the row. Changing the current term later never rewrites an already-stored
 * calendar's term — each calendar keeps its own copy, written once.
 */
class CurrentTerm
{
    private const string KEY = 'current_term';

    private const string CACHE_KEY = 'current-term-value';

    private const Term DEFAULT = Term::FirstTerm;

    /**
     * Returns the current term, or the default if no setting row exists yet
     * (fresh databases / tests that never seed one). Cached indefinitely —
     * this is now read on every request via HandleInertiaRequests::share()
     * (the admin navbar's persistent term/year context), so an uncached
     * query here would run on every single page load, including the 5s
     * useDocumentUpdates() poll. Busted in `set()`.
     */
    public static function get(): Term
    {
        $value = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->where('key', self::KEY)->value('value'),
        );

        return $value !== null ? Term::from($value) : self::DEFAULT;
    }

    /**
     * Sets the current term system-wide. Upserts — never creates a duplicate row.
     */
    public static function set(Term $term): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $term->value]);
        Cache::forget(self::CACHE_KEY);
    }
}
