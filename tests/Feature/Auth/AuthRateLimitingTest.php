<?php

use App\Http\Middleware\ThrottleFortifyRoutes;
use App\Models\User;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Brute-force protection on the sign-in surface (FortifyServiceProvider::
 * configureRateLimiting(), ThrottleFortifyRoutes, config/trustedproxy.php).
 * Every attempt below uses a made-up address, so each is a plain failed login:
 * the response is a 302 back to the form (with errors) until a limiter trips, then a 429.
 */
function failedLogin($test, string $email, ?string $ip = null)
{
    return $test
        ->withServerVariables($ip ? ['REMOTE_ADDR' => $ip] : [])
        ->post(route('login.store'), ['email' => $email, 'password' => 'wrong-password']);
}

beforeEach(function () {
    // Pin the proxy trust so the suite does not depend on the machine's .env.
    config(['trustedproxy.proxies' => null]);
});

test('the existing email plus IP limit still stops a sixth attempt in a minute', function () {
    for ($i = 1; $i <= 5; $i++) {
        failedLogin($this, 'victim@example.test', '10.0.0.1')->assertStatus(302);
    }

    failedLogin($this, 'victim@example.test', '10.0.0.1')->assertStatus(429);
});

test('one email is locked after 20 attempts in 15 minutes even when every attempt comes from a different IP', function () {
    // A new IP each time, so the email + IP bucket (5/min) never fills.
    for ($i = 1; $i <= 20; $i++) {
        failedLogin($this, 'victim@example.test', "10.1.0.$i")->assertStatus(302);
    }

    failedLogin($this, 'victim@example.test', '10.1.0.99')->assertStatus(429);

    // Only that account is locked: another email from a fresh IP is untouched.
    failedLogin($this, 'someone-else@example.test', '10.1.0.100')->assertStatus(302);
});

test('the per email limit ignores letter case in the address', function () {
    for ($i = 1; $i <= 20; $i++) {
        failedLogin($this, $i % 2 ? 'Victim@Example.test' : 'victim@example.test', "10.2.0.$i")->assertStatus(302);
    }

    failedLogin($this, 'VICTIM@example.test', '10.2.0.99')->assertStatus(429);
});

test('one IP is limited to 30 attempts a minute across different emails', function () {
    // A different email each time, so neither email bucket ever fills.
    for ($i = 1; $i <= 30; $i++) {
        failedLogin($this, "target-$i@example.test", '10.3.0.1')->assertStatus(302);
    }

    failedLogin($this, 'target-31@example.test', '10.3.0.1')->assertStatus(429);

    // A different IP is unaffected.
    failedLogin($this, 'target-32@example.test', '10.3.0.2')->assertStatus(302);
});

test('a spoofed X-Forwarded-For header does not get around the per IP limit when proxies are not trusted', function () {
    // Same real IP, a fresh fake client IP in the header every time.
    for ($i = 1; $i <= 30; $i++) {
        $this->withHeader('X-Forwarded-For', "203.0.113.$i")
            ->withServerVariables(['REMOTE_ADDR' => '10.4.0.1'])
            ->post(route('login.store'), ['email' => "target-$i@example.test", 'password' => 'wrong-password'])
            ->assertStatus(302);
    }

    $this->withHeader('X-Forwarded-For', '203.0.113.200')
        ->withServerVariables(['REMOTE_ADDR' => '10.4.0.1'])
        ->post(route('login.store'), ['email' => 'target-31@example.test', 'password' => 'wrong-password'])
        ->assertStatus(429);
});

test('X-Forwarded-For is ignored by default and honoured only when a proxy is configured as trusted', function () {
    $ipSeenFor = function (?string $trusted): ?string {
        config(['trustedproxy.proxies' => $trusted]);

        $seen = null;
        $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.5.0.1']);
        $request->headers->set('X-Forwarded-For', '203.0.113.7');

        (new TrustProxies)->handle($request, function (Request $request) use (&$seen) {
            $seen = $request->ip();

            return response('');
        });

        return $seen;
    };

    expect($ipSeenFor(null))->toBe('10.5.0.1')
        ->and($ipSeenFor('*'))->toBe('203.0.113.7')
        // A specific trusted IP works the same way as "*".
        ->and($ipSeenFor('10.5.0.1'))->toBe('203.0.113.7');
});

test('behind a trusted proxy the client-supplied part of X-Forwarded-For is not used', function () {
    config(['trustedproxy.proxies' => '*']);

    $seen = null;
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '10.5.0.1']);
    // The client sent "1.1.1.1"; the real proxy appended the address it saw.
    $request->headers->set('X-Forwarded-For', '1.1.1.1, 198.51.100.9');

    (new TrustProxies)->handle($request, function (Request $request) use (&$seen) {
        $seen = $request->ip();

        return response('');
    });

    expect($seen)->toBe('198.51.100.9');
});

test('requesting a password reset link is throttled per IP', function () {
    for ($i = 1; $i <= 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.6.0.1'])
            ->post(route('password.email'), ['email' => "nobody-$i@example.test"])
            ->assertStatus(302);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.6.0.1'])
        ->post(route('password.email'), ['email' => 'nobody-6@example.test'])
        ->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '10.6.0.2'])
        ->post(route('password.email'), ['email' => 'nobody-7@example.test'])
        ->assertStatus(302);
});

test('one inbox cannot be sent more than 10 reset links an hour from different IPs', function () {
    for ($i = 1; $i <= 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.7.0.$i"])
            ->post(route('password.email'), ['email' => 'inbox@example.test'])
            ->assertStatus(302);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.7.0.99'])
        ->post(route('password.email'), ['email' => 'inbox@example.test'])
        ->assertStatus(429);
});

test('confirming a password is throttled per user', function () {
    $user = User::factory()->create();

    for ($i = 1; $i <= 6; $i++) {
        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
            ->assertStatus(302);
    }

    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
        ->assertStatus(429);

    // Another signed-in user has their own allowance.
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
        ->assertStatus(302);
});

test('the throttle middleware is attached to the Fortify routes and only acts on the two it names', function () {
    expect(config('fortify.middleware'))->toContain(ThrottleFortifyRoutes::class)
        ->and(ThrottleFortifyRoutes::LIMITERS)->toBe([
            'password.email' => 'password-email',
            'password.confirm.store' => 'password-confirm',
        ]);

    foreach (ThrottleFortifyRoutes::LIMITERS as $limiter) {
        expect(RateLimiter::limiter($limiter))->not->toBeNull();
    }
});

test('the login limiter returns the three limits, existing one first', function () {
    $request = Request::create('/login', 'POST', ['email' => 'Mixed@Example.test'], server: ['REMOTE_ADDR' => '10.8.0.1']);

    $limits = RateLimiter::limiter('login')($request);

    expect($limits)->toHaveCount(3)
        ->and([$limits[0]->maxAttempts, $limits[0]->decaySeconds])->toBe([5, 60])
        ->and([$limits[1]->maxAttempts, $limits[1]->decaySeconds])->toBe([20, 900])
        ->and([$limits[2]->maxAttempts, $limits[2]->decaySeconds])->toBe([30, 60])
        ->and($limits[0]->key)->toBe('mixed@example.test|10.8.0.1')
        ->and(array_unique(array_map(fn ($limit) => $limit->key, $limits)))->toHaveCount(3);
});

test('a non-string email cannot break or skip the limiter', function () {
    $request = Request::create('/login', 'POST', ['email' => ['a', 'b']], server: ['REMOTE_ADDR' => '10.9.0.1']);

    expect(RateLimiter::limiter('login')($request))->toHaveCount(3);
});
