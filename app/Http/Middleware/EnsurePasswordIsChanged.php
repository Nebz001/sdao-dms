<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a logged in user whose account is flagged must_change_password
 * (a one time password from provisioning, or an account still on the retired
 * shared default) to the change password page. Everything else is blocked,
 * including the notification bell endpoints and the document pollers, which
 * simply follow the redirect.
 *
 * Writes no flash message on purpose: the change page explains itself, and a
 * redirected poller must not consume or create a flash the next real page
 * should show (see HandleInertiaRequests::share()).
 *
 * The idle timeout has no keep alive endpoint (its clock is client side, see
 * idle-timeout-dialog.tsx), so logout is the only server route it calls.
 */
class EnsurePasswordIsChanged
{
    public const string CODE = 'password_change_required';

    /** @var array<int, string> */
    public const array ALLOWED_ROUTES = [
        'password.change.edit',
        'password.change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'You must change your temporary password before continuing.',
                'code' => self::CODE,
            ], 403);
        }

        // 303 so a blocked PATCH/PUT/DELETE is followed with a GET.
        return redirect()->route('password.change.edit', status: 303);
    }
}
