<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function capturedProfileCode(): string
{
    $code = null;
    Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

test('the code-entry screen renders once an email change is pending', function () {
    $user = User::factory()->create();
    Mail::fake();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'pending@students.nu-lipa.edu.ph',
    ]);

    $response = $this->actingAs($user)->get(route('profile.verify-email'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('email', 'pending@students.nu-lipa.edu.ph'));
});

test('entering the correct code writes the new email and marks it verified', function () {
    $user = User::factory()->create();
    Mail::fake();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'new-verified@students.nu-lipa.edu.ph',
    ]);

    $response = $this->actingAs($user)->post(route('profile.verify-email.store'), [
        'code' => capturedProfileCode(),
    ]);

    $response->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->email)->toBe('new-verified@students.nu-lipa.edu.ph');
    expect($user->email_verified_at)->not->toBeNull();
});

test('entering the wrong code leaves the email unchanged', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;
    Mail::fake();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'wrong-code@students.nu-lipa.edu.ph',
    ]);

    $response = $this->actingAs($user)->post(route('profile.verify-email.store'), [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    expect($user->refresh()->email)->toBe($originalEmail);
});

test('an expired code is rejected and the email stays unchanged', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;
    Mail::fake();

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'expired-change@students.nu-lipa.edu.ph',
    ]);

    $code = capturedProfileCode();
    EmailVerificationCode::where('email', 'expired-change@students.nu-lipa.edu.ph')
        ->update(['expires_at' => now()->subMinute()]);

    $response = $this->actingAs($user)->post(route('profile.verify-email.store'), ['code' => $code]);

    $response->assertSessionHasErrors('code');
    expect($user->refresh()->email)->toBe($originalEmail);
});

test('a personal email is rejected before any code is sent', function () {
    $user = User::factory()->create();
    Mail::fake();

    $response = $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'someone@outlook.com',
    ]);

    $response->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});
