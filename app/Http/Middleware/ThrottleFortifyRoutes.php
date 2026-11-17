<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttles the Fortify routes that ship without a limit (request a reset
 * link, confirm password). Fortify's routes live in vendor and cannot take a
 * `->middleware()` call, and patching the route collection at boot is lost
 * when routes are cached, so this is attached to the whole Fortify route
 * group (config/fortify.php `middleware`) and picks its limiter by route name.
 * The limiters themselves are in FortifyServiceProvider::configureRateLimiting().
 */
class ThrottleFortifyRoutes
{
    /**
     * Route name => the named rate limiter that guards it.
     *
     * @var array<string, string>
     */
    public const array LIMITERS = [
        'password.email' => 'password-email',
        'password.confirm.store' => 'password-confirm',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
