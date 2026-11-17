<?php

use App\Enums\Role;
use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * A student's two addresses change through two separate forms:
 *  - the SCHOOL email (the login) keeps its student-domain rule and its code;
 *  - the PERSONAL email (where notifications go) has no domain rule, but a
 *    change is never written directly: a code is sent to the NEW personal
 *    address first, so a typo cannot silently stop their mail.
 */
function studentWithPersonalEmail(string $personal = 'old.personal@gmail.com'): User
{
    return User::factory()->create([
        'email' => 'sam.tan@students.nu-lipa.edu.ph',
        'personal_email' => $personal,
    ]);
}

/** @return array<int, string> every address a verification code mail was queued to */
function queuedCodeRecipients(): array
{
    return Mail::queued(EmailVerificationCodeMail::class)
        ->flatMap(fn (EmailVerificationCodeMail $mail) => collect($mail->to)->pluck('address'))
        ->all();
}

function personalCodeFromMail(): string
{
    $code = null;
    Mail::assertQueued(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    return $code;
}

// ── Personal email: no domain rule, valid email required ────────────────

test('a student can change their personal email to any valid address, school or not', function (string $address) {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)
        ->patch(route('profile.personal-email.update'), ['personal_email' => $address])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.verify-personal-email'));
})->with([
    'gmail' => 'sam.new@gmail.com',
    'yahoo' => 'sam@yahoo.com',
    'a custom domain' => 'sam@my-domain.dev',
    'a school address' => 'sam.other@students.nu-lipa.edu.ph',
]);

test('the personal email must be a valid address and is required', function (string $address) {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)
        ->patch(route('profile.personal-email.update'), ['personal_email' => $address])
        ->assertSessionHasErrors('personal_email');

    Mail::assertNothingQueued();
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');
})->with(['empty' => '', 'not an email' => 'sam.new.gmail.com', 'no domain' => 'sam@']);

// ── The code gate ───────────────────────────────────────────────────────

test('a change sends the code to the NEW personal email only, and changes nothing yet', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    expect(queuedCodeRecipients())->toBe(['sam.new@gmail.com']);
    Mail::assertQueued(EmailVerificationCodeMail::class, 1);
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');
    expect($user->fresh()->email)->toBe('sam.tan@students.nu-lipa.edu.ph');
});

test('the code is not sent to the school email or the old personal email', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    expect(queuedCodeRecipients())->not->toContain('sam.tan@students.nu-lipa.edu.ph')
        ->not->toContain('old.personal@gmail.com');
});

test('the code-entry screen shows the new personal address and says the old one keeps working', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();
    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    $this->actingAs($user)->withoutVite()->get(route('profile.verify-personal-email'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/verify-email-change')
            ->where('kind', 'personal')
            ->where('email', 'sam.new@gmail.com'));
});

test('the correct code writes the new personal email and nothing else', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();
    $verifiedAt = $user->email_verified_at;
    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'Sam.New@Gmail.com']);

    $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => personalCodeFromMail()])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('flash.message', 'Notifications will now be sent to your new address.');

    $user->refresh();
    expect($user->personal_email)->toBe('sam.new@gmail.com');
    expect($user->notificationEmail())->toBe('sam.new@gmail.com');
    expect($user->email)->toBe('sam.tan@students.nu-lipa.edu.ph');
    expect($user->email_verified_at->equalTo($verifiedAt))->toBeTrue();
    expect(session()->has('pending_personal_email'))->toBeFalse();
});

test('a wrong code leaves the personal email unchanged, and too many wrong codes lock it out', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();
    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');

    foreach (range(1, (int) config('school.verification_code.max_attempts')) as $ignored) {
        $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => '000000']);
    }

    $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => personalCodeFromMail()])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');
});

test('a school-email code cannot be used to confirm a personal-email change, and vice versa', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name,
        'email' => 'sam.new@students.nu-lipa.edu.ph',
    ]);
    $schoolCode = personalCodeFromMail();

    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    // Without a pending personal change in session this code means nothing here.
    $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => $schoolCode])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');
});

test('resending issues a fresh code to the same new personal address', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();
    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'sam.new@gmail.com']);

    $this->actingAs($user)->post(route('profile.verify-personal-email.resend'))->assertSessionHasNoErrors();

    expect(queuedCodeRecipients())->toBe(['sam.new@gmail.com', 'sam.new@gmail.com']);
    expect(EmailVerificationCode::where('email', 'sam.new@gmail.com')->whereNull('consumed_at')->count())->toBe(1);
});

test('entering the same personal email again is a no-op with no code sent', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'OLD.personal@gmail.com'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('flash.message', 'That is already your personal email.');

    Mail::assertNothingQueued();
});

test('a student with no personal email yet can add one, with a code', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'legacy@students.nu-lipa.edu.ph', 'personal_email' => null]);

    $this->actingAs($user)->patch(route('profile.personal-email.update'), ['personal_email' => 'legacy@gmail.com']);
    expect($user->fresh()->personal_email)->toBeNull();

    $this->actingAs($user)->post(route('profile.verify-personal-email.store'), ['code' => personalCodeFromMail()]);

    expect($user->fresh()->personal_email)->toBe('legacy@gmail.com');
});

test('opening the code screen with nothing pending sends the student back to settings', function () {
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->get(route('profile.verify-personal-email'))
        ->assertRedirect(route('profile.edit'));
});

test('an account that is not a student cannot use the personal email form', function () {
    Mail::fake();
    $approver = User::factory()->create(['email' => 'carl@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $approver->id, 'role' => Role::SdaoMember->value]);

    $this->actingAs($approver)->patch(route('profile.personal-email.update'), ['personal_email' => 'carl@gmail.com'])
        ->assertForbidden();

    Mail::assertNothingQueued();
});

test('a guest cannot reach any personal email route', function () {
    $this->patch(route('profile.personal-email.update'), ['personal_email' => 'a@gmail.com'])->assertRedirect(route('login'));
    $this->get(route('profile.verify-personal-email'))->assertRedirect(route('login'));
});

// ── The settings page ───────────────────────────────────────────────────

test('the settings page gives a student the personal email form and the current value', function () {
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->withoutVite()->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('hasPersonalEmail', true)
            ->where('personalEmail', 'old.personal@gmail.com'));
});

test('the settings page does not offer the personal email to an approver', function () {
    $approver = User::factory()->create(['email' => 'carl@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $approver->id, 'role' => Role::SdaoMember->value]);

    $this->actingAs($approver)->withoutVite()->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('hasPersonalEmail', false));
});

// ── The school email is unchanged: domain rule plus code ────────────────

test('the school email keeps its domain rule: a personal address is refused there', function (string $address) {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name,
        'email' => $address,
    ])->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
    expect($user->fresh()->email)->toBe('sam.tan@students.nu-lipa.edu.ph');
})->with(['gmail' => 'sam@gmail.com', 'a look-alike' => 'sam@students.nu-lipa.edu.ph.evil.com']);

test('a school email change still sends its code to the new school address, and leaves the personal email alone', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => $user->first_name, 'last_name' => $user->last_name,
        'email' => 'sam.renamed@students.nu-lipa.edu.ph',
    ])->assertRedirect(route('profile.verify-email'));

    expect(queuedCodeRecipients())->toBe(['sam.renamed@students.nu-lipa.edu.ph']);

    $this->actingAs($user)->post(route('profile.verify-email.store'), ['code' => personalCodeFromMail()])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe('sam.renamed@students.nu-lipa.edu.ph');
    expect($user->personal_email)->toBe('old.personal@gmail.com');
});

test('saving a name with the school email unchanged touches neither address', function () {
    Mail::fake();
    $user = studentWithPersonalEmail();

    $this->actingAs($user)->patch(route('profile.update'), [
        'first_name' => 'Samuel', 'last_name' => 'Tan',
        'email' => 'SAM.TAN@students.nu-lipa.edu.ph',
    ])->assertRedirect(route('profile.edit'));

    Mail::assertNothingQueued();
    expect($user->fresh()->personal_email)->toBe('old.personal@gmail.com');
});
