<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an already logged in user whose account has been deactivated, on
 * their very next request. Login itself is blocked separately (see
 * App\Actions\Fortify\AttemptToAuthenticate), this covers sessions and
 * remember me cookies that were alive when the account was deactivated.
 *
 * An Inertia request (page visit, partial reload, the document pollers) gets
 * Inertia::location(), a 409 the client follows as a full page load of the
 * login page, so it never meets the non Inertia response dialog. Only a plain
 * fetch or API call gets the 401 JSON.
 */
class EnsureAccountIsActive
{
    public const string CODE = 'account_deactivated';

    public const string MESSAGE = 'This account has been deactivated. Contact SDAO if you think this is a mistake.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isDeactivated()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            $this->flashMessage($request);

            return Inertia::location(route('login'));
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE, 'code' => self::CODE], 401);
        }

        $this->flashMessage($request);

        return redirect()->route('login');
    }

    private function flashMessage(Request $request): void
    {
        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag(['email' => [self::MESSAGE]])));
    }
}
