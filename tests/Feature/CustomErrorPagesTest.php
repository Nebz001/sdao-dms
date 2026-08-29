<?php

use App\Models\Organization;
use App\Support\ErrorPageStatuses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * One representative Throwable per status in ErrorPageStatuses::HANDLED,
 * chosen to match how that status actually arises in this app where a real
 * trigger exists (AuthorizationException for 403 — what `can:` middleware
 * throws; ModelNotFoundException for 404 — implicit route-model-binding
 * failure; TokenMismatchException for 419 — CSRF/session expiry), falling
 * back to a generic HttpException of that code for statuses with no natural
 * Laravel trigger (502/504 are proxy-layer codes Laravel itself never
 * raises, but AppServiceProvider::configureErrorPages() still covers them
 * defensively).
 *
 * 401 deliberately uses a plain HttpException rather than
 * Illuminate\Auth\AuthenticationException: Laravel's ApplicationBuilder
 * registers a default `redirectGuestsTo(route('login'))` on every app
 * (bootstrap/app.php never overrides it), so a real AuthenticationException
 * on a non-api route always redirects (302) to the login page instead of
 * rendering as 401 — which is correct, existing behavior this feature must
 * not change. The only way this app ever surfaces a bare 401 is an explicit
 * `abort(401, ...)`, which throws exactly this.
 */
function errorPageTrigger(int $status): Throwable
{
    return match ($status) {
        400 => new BadRequestHttpException('Bad request.'),
        401 => new HttpException(401, 'Please log in.'),
        403 => new AuthorizationException('This action is unauthorized.'),
        404 => tap(new ModelNotFoundException, fn ($e) => $e->setModel(Organization::class)),
        405 => new MethodNotAllowedHttpException(['GET'], 'Method not allowed.'),
        408 => new HttpException(408, 'Request timeout.'),
        409 => new ConflictHttpException('Conflict.'),
        419 => new TokenMismatchException('Page expired.'),
        422 => new HttpException(422, 'Unprocessable entity.'),
        429 => new TooManyRequestsHttpException(null, 'Too many requests.'),
        500 => new RuntimeException('Something broke.'),
        502 => new HttpException(502, 'Bad gateway.'),
        503 => new ServiceUnavailableHttpException(null, 'Service unavailable.'),
        504 => new HttpException(504, 'Gateway timeout.'),
        default => throw new InvalidArgumentException("No trigger configured for status {$status}."),
    };
}

beforeEach(function () {
    // A single ad-hoc route, run through the real 'web' middleware group
    // (session, HandleInertiaRequests, etc.) exactly like any real route —
    // this exercises AppServiceProvider::configureErrorPages() through the
    // genuine exception-handling pipeline rather than calling the handler
    // in isolation.
    Route::middleware('web')
        ->get('/__error-page-test/{status}', fn (string $status) => throw errorPageTrigger((int) $status))
        ->where('status', '[0-9]+');

    Route::middleware('web')
        ->get('/api/__error-page-test/{status}', fn (string $status) => throw errorPageTrigger((int) $status))
        ->where('status', '[0-9]+');
});

it('renders every handled status through the shared Inertia error page, not Laravel\'s default view', function (int $status) {
    $response = $this->get("/__error-page-test/{$status}")
        ->assertStatus($status);

    // The default Laravel/Symfony error view is a full HTML document with
    // no Inertia page object embedded — assertInertia() itself fails with a
    // clear message if that's what came back, but this pins the negative
    // case explicitly.
    expect($response->getContent())->not->toContain('Illuminate\Foundation');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('errors/error')
        ->where('status', $status)
    );
})->with(ErrorPageStatuses::HANDLED);

it('leaves api/* error responses as JSON, never the Inertia error page', function () {
    $response = $this->getJson('/api/__error-page-test/404');

    $response->assertStatus(404);
    expect($response->headers->get('content-type'))->toContain('application/json');
    expect($response->getContent())->not->toContain('errors/error');
});

it('returns a real 404 through the Inertia error page for an unmatched route', function () {
    $this->get('/this-route-definitely-does-not-exist')
        ->assertStatus(404)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 404)
        );
});

it('returns a real 405 through the Inertia error page for a mismatched HTTP verb', function () {
    // The home route only accepts GET.
    $this->post('/')
        ->assertStatus(405)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 405)
        );
});

it('returns a real 429 through the Inertia error page once the register rate limit is exceeded', function () {
    // RateLimiter::for('register') in FortifyServiceProvider allows 5/min by IP.
    for ($i = 0; $i < 5; $i++) {
        $this->post('/register', []);
    }

    $this->post('/register', [])
        ->assertStatus(429)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error')
            ->where('status', 429)
        );
});
