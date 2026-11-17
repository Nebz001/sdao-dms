<?php

namespace App\Providers;

use App\Actions\Fortify\AttemptToAuthenticate;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configurePasskeys();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Swaps in our AttemptToAuthenticate so a failed login can tell the
        // user which field was wrong (see PLAN.md — login error specificity
        // decision), instead of Fortify's single vague message. Everything
        // else in the pipeline is Fortify's own default, unchanged — this
        // must stay in sync with vendor/laravel/fortify/src/Http/Controllers/
        // AuthenticatedSessionController.php::loginPipeline().
        Fortify::authenticateThrough(fn (Request $request) => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));
    }

    /**
     * A deactivated account cannot sign in with a passkey either. Fortify's
     * own message for a denied passkey login is fixed and generic.
     */
    private function configurePasskeys(): void
    {
        Passkeys::authorizeLoginUsing(fn (Request $request, ?User $user) => $user !== null && ! $user->isDeactivated());
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * The submitted username/email as a string, so a crafted array or null
     * input can never break the limiter (and so skip it).
     */
    private function loginIdentifier(Request $request, ?string $field = null): string
    {
        $value = $request->input($field ?? Fortify::username());

        return is_string($value) ? $value : '';
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        // Login is capped on three independent axes (the web form and the
        // mobile api login share this limiter). The first is Fortify's own
        // email + IP bucket, kept exactly as it was; the other two stop
        // attacks that bucket cannot see: one account guessed from many IPs
        // (per email) and one IP spraying many accounts (per IP). Each key
        // carries its own prefix so the three counters never collide.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower($this->loginIdentifier($request));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(5)->by(Str::transliterate($email.'|'.$ip)),
                Limit::perMinutes(15, 20)->by('login-email|'.Str::transliterate($email)),
                Limit::perMinute(30)->by('login-ip|'.$ip),
            ];
        });

        // Requesting a reset link: per IP, and per target address so one
        // inbox cannot be flooded from many IPs.
        RateLimiter::for('password-email', function (Request $request) {
            $email = Str::transliterate(Str::lower($this->loginIdentifier($request, 'email')));

            return [
                Limit::perMinute(5)->by('password-email-ip|'.$request->ip()),
                Limit::perHour(10)->by('password-email-address|'.$email),
            ];
        });

        // Confirming a password is a password guess against a signed-in
        // account: per user, falling back to the IP for a stray guest call.
        RateLimiter::for('password-confirm', function (Request $request) {
            return Limit::perMinute(6)->by('password-confirm|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Governs both requesting the first code (registration/profile-email
        // -change) and every "send a new code" resend — keyed by the target
        // address so one email can't be bombarded regardless of which flow.
        RateLimiter::for('email-code-resend', function (Request $request) {
            $email = Str::lower((string) (
                $request->session()->get('pending_registration_email')
                ?? $request->session()->get('pending_profile_email')
                ?? $request->user()?->email
            ));

            return [
                Limit::perMinute(1)->by($email),
                Limit::perHour(5)->by($email),
            ];
        });
    }
}
