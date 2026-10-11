<?php

namespace App\Http\Middleware;

use App\Support\PrivacyNotice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a logged in user to the Privacy Notice until they accept it in this
 * login session. Everything else is blocked: direct urls, the back button,
 * Inertia visits and partial reloads, and background fetches (which get a 403
 * json instead of a redirect they would silently follow).
 *
 * A blocked page visit is remembered so accepting lands where the user was
 * going. Partial reloads and pollers are not remembered, they are not places.
 */
class EnsurePrivacyNoticeAccepted
{
    public const string CODE = 'privacy_notice_required';

    /** @var array<int, string> */
    public const array ALLOWED_ROUTES = [
        'privacy-notice.show',
        'privacy-notice.accept',
        'privacy-notice.decline',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || PrivacyNotice::isAccepted($request->session())) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        $isInertia = (bool) $request->header('X-Inertia');

        if ($request->expectsJson() && ! $isInertia) {
            return response()->json([
                'message' => 'You must accept the Privacy Notice before continuing.',
                'code' => self::CODE,
            ], 403);
        }

        if ($request->isMethod('GET') && ! $request->header('X-Inertia-Partial-Component')) {
            $request->session()->put(PrivacyNotice::INTENDED_KEY, $request->fullUrl());
        }

        // 303 so a blocked PATCH/PUT/DELETE is followed with a GET.
        return redirect()->route('privacy-notice.show', status: 303);
    }
}
