<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Attachment storage faking (Phase 2 item 8)
|--------------------------------------------------------------------------
|
| Every Feature test gets a faked local disk automatically — Registration,
| Renewal, and After-Activity Report submissions now require real uploaded
| files, so any test that reaches those write paths needs Storage::fake()
| in effect. Scoped to Feature only (Unit tests don't touch the DB or
| storage), same scoping as RefreshDatabase above.
|
*/

beforeEach(function () {
    Storage::fake('local');
})->in('Feature');

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

function something()
{
    // ..
}
