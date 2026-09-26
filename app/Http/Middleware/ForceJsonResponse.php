<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every /api request to be treated as expecting JSON, regardless of
 * whether the client actually sent an Accept header. Belt-and-suspenders
 * alongside bootstrap/app.php's `shouldRenderJsonWhen(is('api/*'))`: that
 * callback alone already makes the exception handler render JSON for
 * every api/* response, but this also makes `$request->expectsJson()`
 * itself true — the same signal `Illuminate\Auth\Middleware\Authenticate`
 * uses to decide whether to redirect a guest instead of just failing.
 * Without either of these, a mobile client that forgets to send `Accept:
 * application/json` would otherwise be redirected toward the web login
 * page instead of getting a 401.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
