<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function startRegistration(string $email): void
{
    test()->post(route('register.store'), [
        'name' => 'Test User',
        'email' => $email,
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

function capturedCode(): string
{
    $code = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('too many wrong codes locks out further attempts, even the correct one', function () {
    Mail::fake();
    $email = 'lockout@students.nu-lipa.edu.ph';
    startRegistration($email);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('register.verify.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $response = $this->post(route('register.verify.store'), ['code' => capturedCode()]);

    $response->assertSessionHasErrors('code');
    expect(User::where('email', $email)->exists())->toBeFalse();
});

test('an expired code is rejected even when correct', function () {
    Mail::fake();
    $email = 'expired@students.nu-lipa.edu.ph';
    startRegistration($email);
    $code = capturedCode();

    EmailVerificationCode::where('email', $email)->update(['expires_at' => now()->subMinute()]);

    $response = $this->post(route('register.verify.store'), ['code' => $code]);

    $response->assertSessionHasErrors('code');
    expect(User::where('email', $email)->exists())->toBeFalse();
});

test('resending issues a new code and the old one stops working', function () {
    Mail::fake();
    $email = 'resend@students.nu-lipa.edu.ph';
    startRegistration($email);
    $firstCode = capturedCode();

    $this->post(route('register.verify.resend'));

    Mail::assertSent(EmailVerificationCodeMail::class, 2);

    $this->post(route('register.verify.store'), ['code' => $firstCode])
        ->assertSessionHasErrors('code');
});

test('the resent code verifies successfully', function () {
    Mail::fake();
    $email = 'resend-success@students.nu-lipa.edu.ph';
    startRegistration($email);

    $this->post(route('register.verify.resend'));

    $secondCode = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$secondCode) {
        $secondCode = $mail->code;

        return true;
    });

    $response = $this->post(route('register.verify.store'), ['code' => $secondCode]);

    $response->assertRedirect(route('dashboard'));
    expect(User::where('email', $email)->exists())->toBeTrue();
});

test('resend is rate-limited within its cooldown window', function () {
    Mail::fake();
    $email = 'resend-throttled@students.nu-lipa.edu.ph';
    startRegistration($email);

    $this->post(route('register.verify.resend'))->assertSessionHasNoErrors();
    $response = $this->post(route('register.verify.resend'));

    $response->assertTooManyRequests();
});

test('trying to verify with no pending registration redirects back to the register form', function () {
    $response = $this->post(route('register.verify.store'), ['code' => '123456']);

    $response->assertRedirect(route('register'));
});
