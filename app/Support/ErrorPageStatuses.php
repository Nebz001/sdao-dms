<?php

namespace App\Support;

/**
 * The HTTP status codes rendered through the shared Inertia error page
 * (resources/js/pages/errors/error.tsx) instead of Laravel's default Blade
 * error views. Registered against this list in
 * AppServiceProvider::configureErrorPages() and reused here so tests assert
 * against the same source of truth rather than a duplicated literal list.
 */
class ErrorPageStatuses
{
    /** @var list<int> */
    public const array HANDLED = [
        400, 401, 403, 404, 405, 408, 409, 419, 422, 429, 500, 502, 503, 504,
    ];
}
