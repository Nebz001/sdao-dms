<?php

use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Laravel\Fortify\Features;

/**
 * Regression coverage for the "correct 2FA code rejected" bug: visiting
 * /settings/security a second time before confirming used to silently wipe
 * the in-progress two_factor_secret (Laravel\Fortify\
 * InteractsWithTwoFactorState::neverFinishedConfirmingTwoFactorAuthentication()'s
 * exact-Unix-second comparison), so a genuinely correct code would then fail
 * at Fortify's own empty-secret guard. Fixed by overriding that method in
 * App\Http\Requests\Settings\TwoFactorAuthenticationRequest with a
 * grace-period window instead. See the plan/investigation notes for the
 * full trace.
 */
beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $this->user = User::factory()->create();
});

test('two factor setup survives revisiting the security page before confirmation', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    // Load the page once (stamps two_factor_empty_at), enable 2FA, then let
    // Inertia's follow-up GET (Fortify's back() response) stamp
    // two_factor_confirming_at — this first cycle is the one request the
    // upstream check was designed to survive.
    $this->get(route('security.edit'));
    $this->post(route('two-factor.enable'));
    $this->get(route('security.edit'));

    // Simulate real elapsed time before the second visit: time() has
    // 1-second resolution and Pest's sequential requests can easily land
    // within the same wall-clock second, which would mask the bug entirely
    // (the upstream/original check is `confirming_at != currentTime` — if
    // both requests share a second, that's trivially false either way).
    // Backdating the session value directly is what the real bug actually
    // depends on: a SECOND visit at a later time() than the one that
    // stamped two_factor_confirming_at.
    $this->withSession(['two_factor_confirming_at' => time() - 5])
        ->get(route('security.edit'));

    expect($this->user->fresh()->two_factor_secret)->not->toBeNull();

    $this->post(route('two-factor.confirm'), [
        'code' => currentTwoFactorCodeFor($this->user),
    ])->assertSessionHasNoErrors();

    expect($this->user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('two factor can be confirmed immediately after enabling', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('security.edit'));
    $this->post(route('two-factor.enable'));
    $this->get(route('security.edit'));

    $this->post(route('two-factor.confirm'), [
        'code' => currentTwoFactorCodeFor($this->user),
    ])->assertSessionHasNoErrors();

    expect($this->user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('an abandoned two factor setup is cleaned up after the grace period', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('security.edit'));
    $this->post(route('two-factor.enable'));
    $this->get(route('security.edit'));

    expect($this->user->fresh()->two_factor_secret)->not->toBeNull();

    // Backdate the confirming-state timestamp past the grace window — this
    // directly targets the cleanup rule this fix now owns, rather than
    // waiting 15 real minutes.
    $this->withSession([
        'two_factor_confirming_at' => time() - TwoFactorAuthenticationRequest::CONFIRMATION_GRACE_SECONDS - 60,
    ])->get(route('security.edit'));

    $fresh = $this->user->fresh();
    expect($fresh->two_factor_secret)->toBeNull();
    expect($fresh->two_factor_recovery_codes)->toBeNull();
});

test('an incorrect two factor code is still rejected', function () {
    $this->actingAs($this->user)
        ->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('security.edit'));
    $this->post(route('two-factor.enable'));
    $this->get(route('security.edit'));
    $this->get(route('security.edit'));

    $this->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', ['code']);

    expect($this->user->fresh()->two_factor_confirmed_at)->toBeNull();
});
