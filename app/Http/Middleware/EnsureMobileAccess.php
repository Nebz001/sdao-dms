<?php

namespace App\Http\Middleware;

use App\Identity\MobileAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-checks App\Identity\MobileAccess on EVERY mobile-API request (not
 * only at login), so a role change or an account rejection takes effect
 * immediately — a token issued while an approver had access still gets a
 * 403 the moment they lose it. Logout is deliberately routed outside this
 * middleware, so a token can always be deleted even if access was revoked
 * first.
 */
class EnsureMobileAccess
{
    public function __construct(private readonly MobileAccess $mobileAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->mobileAccess->canAccess($user)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        return $next($request);
    }
}
