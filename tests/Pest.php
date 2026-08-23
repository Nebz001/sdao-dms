<?php

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

/*
|--------------------------------------------------------------------------
| Attachment storage faking (Phase 2 item 8; Supabase migration)
|--------------------------------------------------------------------------
|
| Every Feature test gets faked disks automatically — Registration,
| Renewal, and After-Activity Report submissions now require real uploaded
| files, so any test that reaches those write paths needs Storage::fake()
| in effect. Scoped to Feature only (Unit tests don't touch the DB or
| storage), same scoping as RefreshDatabase above.
|
| Both disks are faked: "supabase" is where AttachmentStorage now writes
| (filesystems.attachments), "local" is kept faked too so tests covering
| pre-migration rows (disk => 'local') can plant/assert files without
| touching the real filesystem.
|
| IMPORTANT: this must be chained onto the SAME pest()->extend()->in() call
| via ->beforeEach(...) — a standalone top-level beforeEach(fn () => ...)
| ->in('Feature') call silently registers nothing (Pest's global beforeEach()
| function binds to the CURRENT file, and ->in() is not a real method on the
| resulting call; it went through Pest's proxy fallback and forwarded 'in()'
| onto the test case, where it's a no-op). Found and fixed while migrating
| attachments to Supabase Storage: the local disk masked this bug for years
| because assertExists()/assertMissing() work against the real 'local'
| filesystem with or without Storage::fake(), so no attachment test's fake
| was ever actually taking effect until this call was corrected.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature')
    ->beforeEach(function () {
        Storage::fake('supabase');
        Storage::fake('local');
    });

/*
|--------------------------------------------------------------------------
| Browser tests
|--------------------------------------------------------------------------
|
| Real-click regression tests (pestphp/pest-plugin-browser). These run
| in-process — Pest's LaravelHttpServer invokes this same booted app's
| HTTP kernel directly (see vendor/pestphp/pest-plugin-browser/src/Drivers/
| LaravelHttpServer.php), so RefreshDatabase's transaction is visible to
| both the test and the page Playwright drives, same as Feature tests.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Phase 2 item 8 — fake uploaded files for every required Registration
 * attachment slot, keyed by slot_key exactly as AttachmentSlots expects.
 * Reused directly (direct action-class calls) or nested under an
 * 'attachments' key in an HTTP payload.
 *
 * @return array<string, UploadedFile>
 */
function registrationAttachmentFiles(): array
{
    return [
        'letter_of_intent' => UploadedFile::fake()->create('letter-of-intent.pdf', 100, 'application/pdf'),
        'application_form' => UploadedFile::fake()->create('application-form.pdf', 100, 'application/pdf'),
        'by_laws' => UploadedFile::fake()->create('by-laws.pdf', 100, 'application/pdf'),
        'officers_list' => UploadedFile::fake()->create('officers-list.pdf', 100, 'application/pdf'),
        'dean_endorsement_letter' => UploadedFile::fake()->create('dean-endorsement.pdf', 100, 'application/pdf'),
        'proposed_projects_budget' => UploadedFile::fake()->create('proposed-projects-budget.pdf', 100, 'application/pdf'),
    ];
}

/**
 * Renewal's required list: Registration's 6 slots, plus 3 more.
 *
 * @return array<string, UploadedFile>
 */
function renewalAttachmentFiles(): array
{
    return [
        ...registrationAttachmentFiles(),
        'past_projects_list' => UploadedFile::fake()->create('past-projects-list.pdf', 100, 'application/pdf'),
        'financial_statement' => UploadedFile::fake()->create('financial-statement.pdf', 100, 'application/pdf'),
        'evaluation_summary' => UploadedFile::fake()->create('evaluation-summary.pdf', 100, 'application/pdf'),
    ];
}

/**
 * After-Activity Report's 3 required slots — Photos is multi-file.
 *
 * @return array<string, UploadedFile|array<int, UploadedFile>>
 */
function reportAttachmentFiles(): array
{
    return [
        // Uses create() rather than image() — the GD extension (required by
        // image()) isn't guaranteed available; create() with an explicit
        // mime type is sufficient since fake uploads run in Symfony's "test"
        // mode, where mime-validation trusts the declared type rather than
        // sniffing file content.
        'photos' => [UploadedFile::fake()->create('photo-1.jpg', 200, 'image/jpeg')],
        'evaluation_form' => UploadedFile::fake()->create('evaluation-form.pdf', 100, 'application/pdf'),
        'attendance_sheet' => UploadedFile::fake()->create('attendance-sheet.pdf', 100, 'application/pdf'),
    ];
}

/**
 * Generates the current, correctly-valid 6-digit TOTP code for a user's
 * confirmed-or-unconfirmed 2FA secret — for HTTP-level tests that must
 * drive Fortify's real confirm endpoint rather than pre-seed
 * two_factor_confirmed_at. Reads $user->fresh() since the caller typically
 * just enabled 2FA via an HTTP call, and the in-memory $user instance won't
 * reflect the secret Fortify wrote.
 */
function currentTwoFactorCodeFor(User $user): string
{
    return app(Google2FA::class)->getCurrentOtp(
        Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret)
    );
}

/**
 * Drives the real two-step self-registration HTTP flow end to end: POST
 * register.store (validates the school email, issues a code, creates NO
 * User row yet) then POST register.verify.store with the code captured off
 * the faked EmailVerificationCodeMail. Calls Mail::fake() itself, so don't
 * assert on other mail in the same test without re-faking afterward.
 *
 * @return array{0: User, 1: array<string, string>} the created user and the payload used
 */
function registerViaHttp(array $overrides = []): array
{
    Mail::fake();

    $payload = array_merge([
        'name' => 'Test Student',
        'email' => 'student-'.Str::random(10).'@students.nu-lipa.edu.ph',
        'id_number' => fake()->unique()->numerify('####-######'),
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);

    test()->post(route('register.store'), $payload);

    $code = null;
    Mail::assertQueued(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return true;
    });

    test()->post(route('register.verify.store'), ['code' => $code]);

    return [User::where('email', $payload['email'])->firstOrFail(), $payload];
}

function something()
{
    // ..
}
