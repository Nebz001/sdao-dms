<?php

namespace App\Actions\Fortify;

use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable as BaseRedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * Overrides only the credential-validation failure branch of Fortify's own
 * RedirectIfTwoFactorAuthenticatable (vendor/laravel/fortify/src/Actions/
 * RedirectIfTwoFactorAuthenticatable.php) to give a field-specific error
 * instead of the generic one. See AttemptToAuthenticate.php in this
 * directory for the full rationale — this class exists alongside it, not
 * instead of it, because of a Fortify pipeline detail that isn't obvious
 * from the outside:
 *
 * When the twoFactorAuthentication feature is enabled (as it is here),
 * this class's validateCredentials() runs BEFORE AttemptToAuthenticate on
 * EVERY login attempt — 2FA or not — because Fortify has to fully validate
 * the credentials first just to know whether the matched user has 2FA
 * enabled. On bad credentials, THIS is what throws — AttemptToAuthenticate
 * is never reached at all in that case, since the pipeline stops here.
 * (Confirmed empirically: swapping only AttemptToAuthenticate left the
 * generic "These credentials do not match our records." message showing
 * unchanged, because this class was still the one failing first.)
 */
class RedirectIfTwoFactorAuthenticatable extends BaseRedirectIfTwoFactorAuthenticatable
{
    /**
     * @param  Request  $request
     */
    protected function validateCredentials($request): mixed
    {
        if (Fortify::$authenticateUsingCallback) {
            return parent::validateCredentials($request);
        }

        $provider = $this->guard->getProvider();
        $user = $provider->retrieveByCredentials($request->only(Fortify::username(), 'password'));

        if (! $user) {
            event(new Failed(config('fortify.guard', 'web'), null, $request->only(Fortify::username(), 'password')));

            throw ValidationException::withMessages([
                Fortify::username() => [trans('passwords.user')],
            ]);
        }

        if (! $provider->validateCredentials($user, ['password' => $request->password])) {
            event(new Failed(config('fortify.guard', 'web'), $user, $request->only(Fortify::username(), 'password')));

            throw ValidationException::withMessages([
                'password' => [trans('auth.password')],
            ]);
        }

        if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
            $provider->rehashPasswordIfRequired($user, ['password' => $request->password]);
        }

        return $user;
    }
}
