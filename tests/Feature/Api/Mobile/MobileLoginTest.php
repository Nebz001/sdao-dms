<?php

use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
});

function validTwoFactorSecret(): string
{
    return app(Google2FA::class)->generateSecretKey();
}

test('a verified adviser can log in and receives a token', function () {
    $response = $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ]);

    $response->assertOk();
    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect($user->tokens()->count())->toBe(1);
});

test('an unknown email gives a 422 on the email field', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'nobody@nu-lipa.edu.ph',
        'password' => 'whatever',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('a wrong password gives a 422 on the password field', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'wrong-password',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('a missing device_name gives a 422', function () {
    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_name');
});

test('a student cannot log in to the mobile app', function () {
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $this->postJson('/api/mobile/login', [
        'email' => $student->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(403)
        ->assertJson(['message' => 'You are not allowed to perform this action.']);
});

test('an approver with an unverified email cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['email_verified_at' => null])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('an approver whose account is not Verified cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['account_status' => 'unverified'])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('an approver on a non-staff email domain cannot log in', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['email' => 'adviser-one@students.nu-lipa.edu.ph'])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(403);
});

test('an unconfirmed 2FA setup does not block login', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt(validTwoFactorSecret()),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertOk();
});

test('a confirmed 2FA account without a code gets a 422 asking for one', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt(validTwoFactorSecret()),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('a confirmed 2FA account with a valid TOTP code can log in', function () {
    $secret = validTwoFactorSecret();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
        'code' => $code,
    ])->assertOk();
});

test('a confirmed 2FA account with an invalid code gets a 422', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt(validTwoFactorSecret()),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
        'code' => '000000',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('a valid recovery code can be used once and then rejected the second time', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill([
        'two_factor_secret' => encrypt(validTwoFactorSecret()),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one', 'recovery-code-two'])),
    ])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
        'recovery_code' => 'recovery-code-one',
    ])->assertOk();

    $adviser->refresh();
    expect($adviser->recoveryCodes())->not->toContain('recovery-code-one');

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
        'recovery_code' => 'recovery-code-one',
    ])->assertStatus(422);
});

test('login is throttled after 5 attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/mobile/login', [
            'email' => 'adviser-one@nu-lipa.edu.ph',
            'password' => 'wrong-password',
            'device_name' => 'Test Phone',
        ]);
    }

    $this->postJson('/api/mobile/login', [
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])->assertStatus(429);
});
