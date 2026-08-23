<?php

use App\Enums\AccountStatus;
use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('a submission with a personal email is rejected before any code is generated', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@gmail.com',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingQueued();
    expect(User::where('email', 'test@gmail.com')->exists())->toBeFalse();
});

test('an ID number that does not match the 4-digit-year-dash-6-digit format is rejected', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'bad-id@students.nu-lipa.edu.ph',
        'id_number' => '02000123456',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('id_number');
    Mail::assertNothingQueued();
});

test('a valid submission sends a code and creates no user yet', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test.user@students.nu-lipa.edu.ph',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('register.verify'));
    expect(User::where('email', 'test.user@students.nu-lipa.edu.ph')->exists())->toBeFalse();
    Mail::assertQueued(EmailVerificationCodeMail::class);
});

test('entering the correct code creates the account, verifies the email, and logs the user in', function () {
    [$user] = registerViaHttp(['email' => 'code-match@students.nu-lipa.edu.ph']);

    $this->assertAuthenticatedAs($user);
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->account_status)->toBe(AccountStatus::Unverified);
});

test('entering the wrong code fails and creates no account', function () {
    Mail::fake();
    $email = 'wrong-code@students.nu-lipa.edu.ph';

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => $email,
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response = $this->post(route('register.verify.store'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect(User::where('email', $email)->exists())->toBeFalse();
    $this->assertGuest();
});

test('a code can only be used once', function () {
    Mail::fake();
    $email = 'reuse-code@students.nu-lipa.edu.ph';

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => $email,
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $code = null;
    Mail::assertQueued(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    $this->post(route('register.verify.store'), ['code' => $code]);
    $this->post(route('logout'));

    // A successful verify clears the pending-registration session key, so
    // re-establish it to exercise the controller's code-check path directly
    // — the record itself (consumed_at) is what must reject reuse here.
    $response = $this
        ->withSession(['pending_registration_email' => $email])
        ->post(route('register.verify.store'), ['code' => $code]);

    $response->assertSessionHasErrors('code');
});
