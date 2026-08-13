<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The "join an existing organization" choice (register.tsx's first
 * fieldset) rides along in EmailVerificationCode's payload and only ever
 * affects verifyStore()'s post-verification redirect — see
 * App\Http\Controllers\Auth\RegistrationController's docblocks.
 */
function startRegistrationWithIntent(?string $intentedPath): string
{
    Mail::fake();
    $email = 'student-'.Str::random(10).'@students.nu-lipa.edu.ph';

    $payload = [
        'name' => 'Test Student',
        'email' => $email,
        'id_number' => fake()->unique()->numerify('####-######'),
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    if ($intentedPath !== null) {
        $payload['intended_path'] = $intentedPath;
    }

    test()->post(route('register.store'), $payload);

    return $email;
}

function capturedRegistrationCode(): string
{
    $code = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('choosing "register a new organization" redirects to the dashboard, unchanged', function () {
    $email = startRegistrationWithIntent('register_new');

    $response = test()->post(route('register.verify.store'), ['code' => capturedRegistrationCode()]);

    $response->assertRedirect(route('dashboard'));
    expect(User::where('email', $email)->exists())->toBeTrue();
});

test('choosing "join an existing organization" redirects to the join page instead', function () {
    $email = startRegistrationWithIntent('join_existing');

    $response = test()->post(route('register.verify.store'), ['code' => capturedRegistrationCode()]);

    $response->assertRedirect(route('organizations.join.create'));
    expect(User::where('email', $email)->exists())->toBeTrue();
});

test('a request with no intended_path at all still redirects to the dashboard (back-compat)', function () {
    $email = startRegistrationWithIntent(null);

    $response = test()->post(route('register.verify.store'), ['code' => capturedRegistrationCode()]);

    $response->assertRedirect(route('dashboard'));
    expect(User::where('email', $email)->exists())->toBeTrue();
});

test('an invalid intended_path value is rejected by validation', function () {
    Mail::fake();

    $response = test()->post(route('register.store'), [
        'name' => 'Test Student',
        'email' => 'student-'.Str::random(10).'@students.nu-lipa.edu.ph',
        'id_number' => fake()->unique()->numerify('####-######'),
        'password' => 'password',
        'password_confirmation' => 'password',
        'intended_path' => 'something_else',
    ]);

    $response->assertSessionHasErrors('intended_path');
});
