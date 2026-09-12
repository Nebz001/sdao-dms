<?php

namespace App\Providers;

use App\Approval\Contracts\ApproverNotifier;
use App\Approval\Contracts\SubmitterNotifier;
use App\Approval\Notifications\MailingSubmitterNotifier;
use App\Approval\Notifications\RecordingApproverNotifier;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Support\ErrorPageStatuses;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ApproverNotifier::class, RecordingApproverNotifier::class);
        $this->app->bind(SubmitterNotifier::class, MailingSubmitterNotifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePolicies();
        $this->configureGates();
        $this->configureErrorPages();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configurePolicies(): void
    {
        Gate::policy(Organization::class, DocumentPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }

    /**
     * Gate access to the SDAO admin-provisioning area (Slice 6).
     */
    protected function configureGates(): void
    {
        Gate::define('access-admin', fn (User $user): bool => $user->roleAssignments
            ->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember));
    }

    /**
     * Every status in ErrorPageStatuses::HANDLED renders through the shared
     * `errors/error` Inertia page (see resources/js/pages/errors/error.tsx)
     * so a user who hits one still sees a page that feels like part of the
     * app, instead of Laravel's default Blade error view.
     *
     * Skipped for api/* (already routed to JSON by shouldRenderJsonWhen in
     * bootstrap/app.php — this would otherwise clobber that JSON with an
     * HTML/Inertia page) and in local development, where the framework's
     * own debug page is more useful for chasing down a real bug. Both
     * bypasses fall through to `null`, which leaves Laravel's normal
     * rendering untouched.
     *
     * The one exception to both bypasses: a throttled login attempt (429 on
     * `login.store`, thrown by Fortify's `throttle:login` middleware — see
     * FortifyServiceProvider::configureRateLimiting()) never falls through
     * to the generic error page swap, in ANY environment (including local,
     * so the countdown UI stays testable without a staging deploy) — see
     * renderThrottledLogin().
     */
    protected function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if ($this->isThrottledLoginAttempt($response)) {
                return $this->renderThrottledLogin($response);
            }

            if (app()->environment('local') || $response->request->is('api/*')) {
                return null;
            }

            if (! in_array($response->statusCode(), ErrorPageStatuses::HANDLED, true)) {
                return null;
            }

            $this->ensureSessionStarted($response->request);

            return $response->render('errors/error', [
                'status' => $response->statusCode(),
            ])->withSharedData();
        });
    }

    private function isThrottledLoginAttempt(ExceptionResponse $response): bool
    {
        return $response->statusCode() === 429
            && $response->request->route()?->getName() === 'login.store';
    }

    /**
     * Re-renders the login page itself (same props Fortify's own
     * `Fortify::loginView()` supplies) with an added `retryAfterSeconds`
     * prop, instead of swapping to the generic `errors/error` page — the
     * user stays on the login form and sees a countdown telling them how
     * long to wait, rather than losing their place to a full-page "Slow
     * down a little" swap.
     */
    private function renderThrottledLogin(ExceptionResponse $response): ExceptionResponse
    {
        $retryAfter = $response->exception instanceof HttpExceptionInterface
            ? (int) ($response->exception->getHeaders()['Retry-After'] ?? 0)
            : 0;

        return $response->render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $response->request->session()->get('status'),
            'retryAfterSeconds' => $retryAfter,
        ])->withSharedData();
    }

    /**
     * A 404 for a URI that matches no route at all, or a 405 for one that
     * matches the URI but not the method, never reaches the 'web' middleware
     * group — Laravel only runs group middleware (StartSession included)
     * once a route has actually matched. Without a session, HandleInertia-
     * Requests::share() would crash reading `$request->session()`, and
     * `$request->user()` would read as a guest even for a signed-in user who
     * simply mistyped a URL. Starting the session here, exactly as
     * StartSession itself would have, fixes both — a no-op when a route DID
     * match and the group middleware already ran.
     */
    protected function ensureSessionStarted(Request $request): void
    {
        if ($request->hasSession()) {
            return;
        }

        app(StartSession::class)->handle($request, fn (Request $req) => new Response);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->symbols()
            : null,
        );
    }
}
