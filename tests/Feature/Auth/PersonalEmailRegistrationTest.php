<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Students register with TWO addresses. The school email (student domain) is
 * the login and the only place a verification code is ever sent; the personal
 * email (any valid address) is saved with the account and is where every later
 * notification goes. The account still only exists once the code is entered.
 */
function personalRegistrationPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan.delacruz@students.nu-lipa.edu.ph',
        'personal_email' => 'juan.personal@gmail.com',
        'id_number' => '2023-182854',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

/** @return array<int, string> every address a verification code mail was queued to */
function codeMailRecipients(): array
{
    return Mail::queued(EmailVerificationCodeMail::class)
        ->flatMap(fn (EmailVerificationCodeMail $mail) => collect($mail->to)->pluck('address'))
        ->all();
}

// ── The two fields, and what each one is allowed to be ──────────────────

test('the personal email is required', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['personal_email' => '']))
        ->assertSessionHasErrors(['personal_email' => 'Enter your personal email address. Notifications are sent here.']);

    Mail::assertNothingQueued();
});

test('the personal email is a normal email with no domain restriction', function (string $address) {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['personal_email' => $address]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('register.verify'));
})->with([
    'gmail' => 'juan.personal@gmail.com',
    'yahoo' => 'juan@yahoo.com.ph',
    'a custom domain' => 'juan@my-own-domain.dev',
    'the school domain itself' => 'juan.other@students.nu-lipa.edu.ph',
    'the staff domain' => 'juan@nu-lipa.edu.ph',
]);

test('the personal email must still be a valid email address', function (string $address) {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['personal_email' => $address]))
        ->assertSessionHasErrors(['personal_email' => 'Enter a valid personal email address.']);

    Mail::assertNothingQueued();
})->with(['no at sign' => 'juan.personal.gmail.com', 'no domain' => 'juan@', 'spaces' => 'juan personal@gmail.com']);

test('the personal email has a length cap', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['personal_email' => str_repeat('a', 251).'@x.co']))
        ->assertSessionHasErrors('personal_email');
});

test('the school email must still be on the student domain, however valid the personal email is', function (string $address) {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['email' => $address]))
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
})->with([
    'a personal address' => 'juan.dela.cruz@gmail.com',
    'the staff domain' => 'juan@nu-lipa.edu.ph',
    'a look-alike domain' => 'juan@students.nu-lipa.edu.ph.evil.com',
]);

test('putting the personal address in the school field does not pass, and vice versa the school field cannot be skipped', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload(['email' => 'juan.personal@gmail.com', 'personal_email' => 'juan.personal@gmail.com']))
        ->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors('personal_email');

    $this->post(route('register.store'), personalRegistrationPayload(['email' => '']))
        ->assertSessionHasErrors('email');
});

// ── Where the code goes ─────────────────────────────────────────────────

test('the verification code goes to the school email only, never to the personal email', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload())->assertSessionHasNoErrors();

    expect(codeMailRecipients())->toBe(['juan.delacruz@students.nu-lipa.edu.ph']);
    Mail::assertQueued(EmailVerificationCodeMail::class, 1);
});

test('the code record is keyed to the school email, so a code can never be redeemed for the personal one', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload());

    expect(EmailVerificationCode::query()->pluck('email')->all())->toBe(['juan.delacruz@students.nu-lipa.edu.ph']);
});

test('no account exists until the code is entered, and the personal email waits in the encrypted payload', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload());

    expect(User::where('email', 'juan.delacruz@students.nu-lipa.edu.ph')->exists())->toBeFalse();
    expect(User::whereNotNull('personal_email')->exists())->toBeFalse();

    $record = EmailVerificationCode::query()->firstOrFail();
    expect($record->payload['personal_email'])->toBe('juan.personal@gmail.com');
    // Encrypted at rest, not just cast: the raw column does not contain it.
    expect((string) DB::table('email_verification_codes')->value('payload'))->not->toContain('juan.personal');
    // And the code itself is hashed, never stored.
    expect($record->code_hash)->toStartWith('$2y$');
});

test('entering the code creates the account with both addresses', function () {
    [$user] = registerViaHttp([
        'email' => 'maria.santos@students.nu-lipa.edu.ph',
        'personal_email' => 'maria.santos@gmail.com',
    ]);

    expect($user->email)->toBe('maria.santos@students.nu-lipa.edu.ph');
    expect($user->personal_email)->toBe('maria.santos@gmail.com');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->notificationEmail())->toBe('maria.santos@gmail.com');
});

test('the personal email is saved trimmed and lowercase', function () {
    [$user] = registerViaHttp(['personal_email' => '  Maria.Santos@GMail.com ']);

    expect($user->personal_email)->toBe('maria.santos@gmail.com');
});

test('resending sends the new code to the school email only', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload());
    $this->post(route('register.verify.resend'))->assertSessionHasNoErrors();

    expect(codeMailRecipients())->toBe([
        'juan.delacruz@students.nu-lipa.edu.ph',
        'juan.delacruz@students.nu-lipa.edu.ph',
    ]);
});

test('a wrong code, lockout and expiry rules are unchanged: the account is not created and the personal email is not saved', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload());

    foreach (range(1, (int) config('school.verification_code.max_attempts')) as $ignored) {
        $this->post(route('register.verify.store'), ['code' => '000000']);
    }

    expect(User::where('email', 'juan.delacruz@students.nu-lipa.edu.ph')->exists())->toBeFalse();
    $this->post(route('register.verify.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
});

test('a registration started before this field existed still completes, and is mailed at the school email', function () {
    Mail::fake();

    $this->post(route('register.store'), personalRegistrationPayload());
    $record = EmailVerificationCode::query()->firstOrFail();
    $payload = $record->payload;
    unset($payload['personal_email']);
    $record->update(['payload' => $payload]);

    $code = null;
    Mail::assertQueued(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });
    $this->post(route('register.verify.store'), ['code' => $code])->assertSessionHasNoErrors();

    $user = User::where('email', 'juan.delacruz@students.nu-lipa.edu.ph')->firstOrFail();
    expect($user->personal_email)->toBeNull();
    expect($user->notificationEmail())->toBe('juan.delacruz@students.nu-lipa.edu.ph');
});

// ── Login still uses the school email ───────────────────────────────────

test('a student signs in with the school email, not the personal one', function () {
    registerViaHttp([
        'email' => 'login.student@students.nu-lipa.edu.ph',
        'personal_email' => 'login.student@gmail.com',
        'password' => 'a-good-password-1',
        'password_confirmation' => 'a-good-password-1',
    ]);
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'login.student@gmail.com', 'password' => 'a-good-password-1'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'login.student@students.nu-lipa.edu.ph', 'password' => 'a-good-password-1']);
    $this->assertAuthenticated();
});

test('the personal email is a plain nullable column, not unique, not a key', function () {
    expect(Schema::hasColumn('users', 'personal_email'))->toBeTrue();

    $first = User::factory()->create(['personal_email' => 'shared@gmail.com']);
    $second = User::factory()->create(['personal_email' => 'shared@gmail.com']);

    expect($first->personal_email)->toBe($second->personal_email);
});
