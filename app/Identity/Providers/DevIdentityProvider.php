<?php

namespace App\Identity\Providers;

use App\Identity\Contracts\IdentityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Dev-only identity provider: logs in any seeded user directly, with no
 * password or SSO assertion required. Replaced by an SSO provider in Slice 6.
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
