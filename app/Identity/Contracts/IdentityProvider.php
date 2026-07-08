<?php

namespace App\Identity\Contracts;

use App\Models\User;

/**
 * Boundary between the dev-only login page and the session login/logout
 * mechanics. The stub implementation (`DevIdentityProvider`) logs in any
 * seeded fake account with no credential check.
 *
 * Production users authenticate via Laravel Fortify's own email/password
 * pipeline (Slice 6), which manages `Auth::login()`/logout and session
 * regeneration itself and does not go through this interface — Fortify's
 * routes/controllers are a separate, self-contained path. This interface
 * exists solely so the dev-only login page (never registered in production,
 * see the `!app()->isProduction()` guard in routes/web.php) doesn't call
 * `Auth`/`Session` facades directly.
 */
interface IdentityProvider
{
    /**
     * Log the given user into the application.
     */
    public function login(User $user, bool $remember = false): void;

    /**
     * Log the currently authenticated user out of the application.
     */
    public function logout(): void;
}
