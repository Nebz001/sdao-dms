<?php

namespace App\Identity\Contracts;

use App\Models\User;

/**
 * Stable boundary between the application's login/logout mechanics and the
 * rest of the codebase. The stub implementation uses seeded fake accounts;
 * the SSO implementation (Slice 6) validates a school assertion and provisions
 * the user before delegating here. Only this interface is touched at that swap.
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
