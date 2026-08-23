<?php

use App\Mail\EmailVerificationCodeMail;
use Illuminate\Mail\Markdown;

/*
 * The verification code email was redesigned to add the NU Lipa logo and a
 * high-contrast code panel — see resources/views/vendor/mail/html/header.
 * blade.php and .../verification-code.blade.php. Nothing here fakes Mail;
 * these render the real Markdown pipeline (Mailable::render() -> Illuminate\
 * Mail\Mailer::render()) so a broken component actually fails the test,
 * unlike the Mail::assertQueued()-only coverage elsewhere in this suite.
 */
test('the verification email renders the code prominently with the branded header', function () {
    $mail = new EmailVerificationCodeMail('482913', now()->addMinutes(10));

    $html = $mail->render();

    expect($html)
        ->toContain('Your verification code')
        ->toContain('482913')
        ->toContain('nulp-logo-light-bg.png')
        ->toContain('This code expires at')
        ->toContain("If you didn't request this, you can ignore this email.");
});

/*
 * Mailable::render() only exercises the HTML pass. The plain-text
 * alternative is built separately by Markdown::renderText() against a
 * SEPARATE "text" component namespace (Mailable::buildMarkdownView()) — a
 * missing text/verification-code.blade.php throws "View [mail::
 * verification-code] not found" at send time despite the HTML pass (and
 * Mail::assertQueued()) passing cleanly. This is the regression the html-only
 * test above cannot catch.
 */
test('the verification email text alternative renders the bare code without markup', function () {
    $text = app(Markdown::class)->renderText('mail.email-verification-code', [
        'code' => '482913',
        'expiresAt' => now()->addMinutes(10),
    ]);

    expect((string) $text)
        ->toContain('482913')
        ->not->toContain('<table')
        ->not->toContain('nulp-logo-light-bg.png');
});

/*
 * Overriding the shared framework header (resources/views/vendor/mail/html/
 * header.blade.php) is a deliberate, app-wide decision — see the plan's
 * "Intentional blast radius" note — not scoped to the verification email
 * alone. This dataset pins that every Mailable using <x-mail::message>
 * picks up the logo, so an accidental revert of the header override is
 * caught everywhere at once instead of silently un-branding seven other
 * emails.
 */
test('the branded header renders on every markdown mailable', function (string $view, array $data) {
    $html = app(Markdown::class)->render($view, $data);

    expect((string) $html)->toContain('nulp-logo-light-bg.png');
})->with([
    'email-verification-code' => ['mail.email-verification-code', [
        'code' => '482913',
        'expiresAt' => now()->addMinutes(10),
    ]],
    'approver-hand-off' => ['mail.approver-hand-off', [
        'isResubmission' => false,
        'approverName' => 'Test Approver',
        'formTypeLabel' => 'Registration',
        'organizationName' => 'Test Org',
        'documentTitle' => 'Test Document',
        'reviewUrl' => 'http://localhost/documents/1',
    ]],
    'account-verified' => ['mail.account-verified', [
        'accountName' => 'Test Student',
        'loginUrl' => 'http://localhost/login',
    ]],
    'join-request-received' => ['mail.join-request-received', [
        'recipientName' => 'Test Adviser',
        'studentName' => 'Test Student',
        'studentEmail' => 'student@students.nu-lipa.edu.ph',
        'organizationName' => 'Test Org',
        'reviewUrl' => 'http://localhost/join-requests/1',
    ]],
]);
