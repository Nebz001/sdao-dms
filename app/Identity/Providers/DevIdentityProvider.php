<?php

namespace App\Identity\Providers;

use App\Identity\Contracts\IdentityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Dev-only identity provider: logs in any seeded user directly, with no
 * password required. Used exclusively by the dev-only login page (never
 * registered in production). Real users authenticate via Laravel Fortify's
 * own pipeline (Slice 6), which does not use this class or this interface.
 */
class DevIdentityProvider implements IdentityProvider
{
    public function login(User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);
        Session::regenerate();
    }

    public function logout(): void
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
    }
}
