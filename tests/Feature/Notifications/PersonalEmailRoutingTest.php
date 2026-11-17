<?php

use App\Models\User;
use App\Notifications\AccountRejectedNotification;
use App\Notifications\AccountVerifiedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Which mailbox a message reaches is decided in ONE place, User::notificationEmail()
 * (via routeNotificationForMail()). Everything that proves ownership of the
 * school mailbox (password reset, email verification) is the one exception and
 * always goes to the school email. These tests send through the real mail
 * channel and the array transport, so they see the recipient that would
 * actually be mailed.
 */

/**
 * @return array<int, array<int, string>> the recipients of every message sent so far
 */
function mailedRecipients(): array
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($message) => collect($message->getEnvelope()->getRecipients())->map->getAddress()->all())
        ->values()
        ->all();
}

function studentWithBothEmails(): User
{
    return User::factory()->create([
        'email' => 'ana.reyes@students.nu-lipa.edu.ph',
        'personal_email' => 'ana.reyes@gmail.com',
    ]);
}

// ── The accessor ────────────────────────────────────────────────────────

test('notificationEmail is the personal email when there is one, else the account email', function () {
    expect(studentWithBothEmails()->notificationEmail())->toBe('ana.reyes@gmail.com');

    $none = User::factory()->create(['email' => 'old.student@students.nu-lipa.edu.ph', 'personal_email' => null]);
    expect($none->notificationEmail())->toBe('old.student@students.nu-lipa.edu.ph');

    $approver = User::factory()->create(['email' => 'carl@nu-lipa.edu.ph']);
    expect($approver->notificationEmail())->toBe('carl@nu-lipa.edu.ph');
});

test('a blank or whitespace personal email counts as none', function () {
    $user = User::factory()->create(['email' => 'blank@students.nu-lipa.edu.ph']);
    $user->forceFill(['personal_email' => '   '])->save();

    expect($user->fresh()->personal_email)->toBeNull();
    expect($user->fresh()->notificationEmail())->toBe('blank@students.nu-lipa.edu.ph');
});

test('every notification asks routeNotificationForMail, which returns the notification email', function () {
    $user = studentWithBothEmails();

    expect($user->routeNotificationFor('mail', new AccountVerifiedNotification))->toBe('ana.reyes@gmail.com');
    expect($user->routeNotificationFor('mail', new AccountRejectedNotification))->toBe('ana.reyes@gmail.com');
    expect($user->routeNotificationFor('mail'))->toBe('ana.reyes@gmail.com');
});

test('mailbox-proof notifications are routed to the school email, whatever the personal email is', function () {
    $user = studentWithBothEmails();

    expect($user->routeNotificationFor('mail', new ResetPassword('token')))->toBe('ana.reyes@students.nu-lipa.edu.ph');
    expect($user->routeNotificationFor('mail', new VerifyEmail))->toBe('ana.reyes@students.nu-lipa.edu.ph');
    expect(User::MAILBOX_PROOF_NOTIFICATIONS)->toBe([ResetPassword::class, VerifyEmail::class]);
});

// ── Real sends ──────────────────────────────────────────────────────────

test('a notification is mailed at the personal email, not the school email', function (string $notification) {
    $user = studentWithBothEmails();

    Notification::sendNow($user, new $notification);

    expect(mailedRecipients())->toBe([['ana.reyes@gmail.com']]);
})->with([
    'account verified' => AccountVerifiedNotification::class,
    'account rejected' => AccountRejectedNotification::class,
]);

test('a student with no personal email yet is still mailed, at the school email', function () {
    $user = User::factory()->create(['email' => 'legacy@students.nu-lipa.edu.ph', 'personal_email' => null]);

    Notification::sendNow($user, new AccountVerifiedNotification);

    expect(mailedRecipients())->toBe([['legacy@students.nu-lipa.edu.ph']]);
});

test('an approver, who has one address, is mailed there', function () {
    $approver = User::factory()->create(['email' => 'chair@gmail.com', 'personal_email' => null]);

    Notification::sendNow($approver, new AccountVerifiedNotification);

    expect(mailedRecipients())->toBe([['chair@gmail.com']]);
});

test('the password reset link goes to the school email, not the personal one', function () {
    $user = studentWithBothEmails();

    Password::sendResetLink(['email' => $user->email]);

    expect(mailedRecipients())->toBe([['ana.reyes@students.nu-lipa.edu.ph']]);
});

test('the email verification link goes to the school email, not the personal one', function () {
    $user = studentWithBothEmails();

    $user->sendEmailVerificationNotification();

    expect(mailedRecipients())->toBe([['ana.reyes@students.nu-lipa.edu.ph']]);
});

test('the forgot-password form still finds a student by the school email, and not by the personal one', function () {
    $user = studentWithBothEmails();

    $this->post(route('password.email'), ['email' => 'ana.reyes@gmail.com']);
    expect(mailedRecipients())->toBe([]);

    $this->post(route('password.email'), ['email' => $user->email]);
    expect(mailedRecipients())->toBe([['ana.reyes@students.nu-lipa.edu.ph']]);
});

// ── No second source of truth ───────────────────────────────────────────

test('no notification picks its own recipient: each toMail asks the model, none reads ->email', function () {
    $files = glob(app_path('Notifications/*.php'));
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $source = file_get_contents($file);
        $name = basename($file);

        expect($source)->not->toMatch('/->email\b/', "{$name} reads ->email directly");
        expect($source)->not->toContain('personal_email', "{$name} must not pick between the two addresses");

        // Every explicit recipient goes through the one router.
        $recipients = preg_match_all('/->to\(/', $source);
        $routed = preg_match_all('/->to\(\$\w+->routeNotificationFor\(\'mail\', \$this\)\)/', $source);
        expect($routed)->toBe($recipients, "{$name} addresses mail some way other than the router");
    }
});

test('only the personal-email accessor and its router know about personal_email in the app code', function () {
    $allowed = [
        'app/Models/User.php',
        'app/Concerns/ProfileValidationRules.php',
        'app/Http/Controllers/Auth/RegistrationController.php',
        'app/Http/Controllers/Settings/PersonalEmailController.php',
        'app/Http/Controllers/Settings/ProfileController.php',
    ];

    $found = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->filter(fn (SplFileInfo $file) => str_contains(file_get_contents($file->getPathname()), 'personal_email'))
        ->map(fn (SplFileInfo $file) => str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1)))
        ->sort()
        ->values()
        ->all();

    expect(array_diff($found, $allowed))->toBe([]);
});

test('the only direct Mail::to in the app is the verification code, which is addressed explicitly', function () {
    $hits = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        // Comment lines (a docblock mentioning Mail::to()) do not count.
        ->filter(fn (SplFileInfo $file) => preg_match('/^(?!\s*(\*|\/\/)).*Mail::(to|send|raw)\(/m', file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file) => basename($file->getPathname()))
        ->values()
        ->all();

    expect($hits)->toBe(['EmailVerificationCodeService.php']);
});
