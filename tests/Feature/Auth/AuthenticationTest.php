<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a user with leftover two-factor data from before its removal still logs in with just email and password', function () {
    // Regression guard for the 2FA removal: the `two_factor_*` columns are
    // deliberately left in the users table (see the removal plan), so an
    // account that had 2FA configured before removal must still be a
    // completely ordinary login afterward — no challenge redirect, no code
    // required.
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('every two-factor route is gone', function () {
    foreach ([
        ['GET', '/two-factor-challenge'],
        ['POST', '/two-factor-challenge'],
        ['POST', '/user/two-factor-authentication'],
        ['POST', '/user/confirmed-two-factor-authentication'],
        ['DELETE', '/user/two-factor-authentication'],
        ['GET', '/user/two-factor-qr-code'],
        ['GET', '/user/two-factor-secret-key'],
        ['GET', '/user/two-factor-recovery-codes'],
        ['POST', '/user/two-factor-recovery-codes'],
    ] as [$method, $uri]) {
        $this->call($method, $uri)->assertNotFound();
    }
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

// ── Login error specificity (deliberate departure from Fortify's default
// vague message — see PLAN.md for the tradeoff and decision) ────────────────

test('login shows an email-specific error when no account exists for that email', function () {
    $this->post(route('login.store'), [
        'email' => 'nobody@nu-lipa.edu.ph',
        'password' => 'password',
    ])->assertInvalid(['email' => "We can't find a user with that email address."]);

    $this->assertGuest();
});

test('login shows a password-specific error when the email is correct but the password is wrong', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertInvalid(['password' => 'The provided password is incorrect.']);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

// ── Login-specific throttle messaging (Group E backlog) ─────────────────────
// A throttled login attempt stays on the login page itself with a countdown,
// instead of swapping to the generic errors/error page — see
// AppServiceProvider::renderThrottledLogin().

test('a rate-limited login re-renders the login page itself with a positive retryAfterSeconds, not the generic error page', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/login')
        ->where('retryAfterSeconds', fn (int $seconds) => $seconds > 0)
    );
});
