<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Revocation is invoked ONLY from two deliberate, user-initiated call
 * sites (App\Actions\Fortify\ResetUserPassword and
 * SecurityController::update) — never from a generic
 * `wasChanged('password')` model observer. That distinction is what these
 * tests exist to prove, most directly in the last test below: the auth
 * guard's own silent password rehash also changes the `password` column,
 * via a completely different code path, and must never revoke a token.
 */
test('a Fortify password reset revokes every Sanctum token', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->createToken('Phone A');
    $user->createToken('Phone B');

    expect($user->tokens()->count())->toBe(2);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->tokens()->count())->toBe(0);
});

test('a settings password change revokes every Sanctum token', function () {
    $user = User::factory()->create();
    $user->createToken('Phone A');
    $user->createToken('Phone B');

    expect($user->tokens()->count())->toBe(2);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

test('a password reset still succeeds when personal_access_tokens does not exist yet', function () {
    Notification::fake();

    $user = User::factory()->create();

    Schema::dropIfExists('personal_access_tokens');

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('brand-new-password', $user->refresh()->password))->toBeTrue();
});

test('a settings password change still succeeds when personal_access_tokens does not exist yet', function () {
    $user = User::factory()->create();

    Schema::dropIfExists('personal_access_tokens');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('a silent password rehash on login never revokes a token', function () {
    $plaintext = 'CorrectPassw0rd!';

    $user = User::factory()->create();
    $token = $user->createToken('Phone A');

    // Planted at a HIGHER bcrypt cost than the test env's current config
    // (BCRYPT_ROUNDS=4) via a raw query — bypassing Eloquent's own
    // 'hashed' cast, which would otherwise refuse to store a hash whose
    // cost doesn't match the current config (Hash::verifyConfiguration()).
    // This is exactly what a real cost-factor change in production looks
    // like from the database's point of view: an existing row whose hash
    // predates the change.
    DB::table('users')->where('id', $user->id)->update([
        'password' => Hash::make($plaintext, ['rounds' => 10]),
    ]);
    $user->refresh();

    $originalHash = $user->password;

    expect(Hash::needsRehash($originalHash))->toBeTrue();

    // This is the exact call SessionGuard::attempt() makes for a web
    // login, and the exact call a mobile login endpoint would make to
    // verify credentials — not a raw Hash::check().
    $result = Auth::guard('web')->attempt([
        'email' => $user->email,
        'password' => $plaintext,
    ]);

    expect($result)->toBeTrue();

    $user->refresh();

    // The rehash really happened...
    expect($user->password)->not->toBe($originalHash);
    expect(Hash::check($plaintext, $user->password))->toBeTrue();

    // ...but the token from before the login is still there, because
    // rehashPasswordIfRequired() never calls revokeAllApiTokens().
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->id)->toBe($token->accessToken->id);
});
