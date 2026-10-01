<?php

namespace App\Actions\Fortify;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

/**
 * Replaces Fortify's default AttemptToAuthenticate pipeline step (see
 * vendor/laravel/fortify/src/Actions/AttemptToAuthenticate.php) to give a
 * field-specific error instead of the framework's single vague message.
 *
 * Deliberate departure from Fortify's default (and most production apps')
 * posture of keeping login errors vague to resist user enumeration. This app
 * decided to go specific everywhere: registration ("The email has already
 * been taken.") and forgot-password ("We can't find a user with that email
 * address.") already leak account existence via unmodified Fortify/Laravel
 * defaults, so login staying vague bought little. See PLAN.md / login error
 * specificity design notes for the full tradeoff discussion.
 *
 * This is the sole credential-validation step in the pipeline (see
 * FortifyServiceProvider::configureActions()) — it always runs, and always
 * throws the field-specific error itself on failure.
 */
class AttemptToAuthenticate
{
    public function __construct(protected StatefulGuard $guard) {}

    /**
     * @param  LoginRequest  $request
     */
    public function handle($request, callable $next): mixed
    {
        $provider = $this->guard->getProvider();
        $user = $provider->retrieveByCredentials($request->only(Fortify::username(), 'password'));

        if (! $user) {
            // The guard's own attempt() would fire this automatically had we
            // called it — since we're skipping that call for a nonexistent
            // user, fire it ourselves so failed-login monitoring still sees
            // every failure, not just the wrong-password ones.
            event(new Failed(config('fortify.guard', 'web'), null, $request->only(Fortify::username(), 'password')));

            throw ValidationException::withMessages([
                Fortify::username() => [trans('passwords.user')],
            ]);
        }

        // A deactivated account is checked here, before any session is
        // created, so the guard's attempt() below never runs for it. The
        // credentials are validated first so the specific message is only
        // shown to someone who really holds the password, never to a guesser.
        if ($user instanceof User && $user->isDeactivated()) {
            if (! $provider->validateCredentials($user, $request->only(Fortify::username(), 'password'))) {
                throw ValidationException::withMessages(['password' => [trans('auth.password')]]);
            }

            throw ValidationException::withMessages([Fortify::username() => [EnsureAccountIsActive::MESSAGE]]);
        }

        if (! $this->guard->attempt(
            $request->only(Fortify::username(), 'password'),
            $request->boolean('remember'),
        )) {
            throw ValidationException::withMessages([
                'password' => [trans('auth.password')],
            ]);
        }

        return $next($request);
    }
}
