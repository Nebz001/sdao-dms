<?php

use App\Enums\FormType;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Coverage for `attachments:migrate-to-supabase` (the one-time backfill for
 * document_attachments rows still on the 'local' disk, predating the
 * Supabase migration — see AttachmentStorage's class docblock).
 *
 * `Storage::fake()` never touches config('filesystems.disks.supabase.*') —
 * only the registered disk driver is swapped — so the command's
 * assertTargetConfigured() guard would otherwise depend on whatever real
 * SUPABASE_STORAGE_* values happen to be in the developer's .env. Pinning
 * them here decouples the suite from that.
 */
beforeEach(function () {
    config([
        'filesystems.disks.supabase.bucket' => 'test-bucket',
        'filesystems.disks.supabase.key' => 'test-key',
        'filesystems.disks.supabase.endpoint' => 'https://test.supabase.local',
    ]);
});

/**
 * Plants a legacy local-disk attachment with a realistic, production-shaped
 * path — the factory's own default path is a flat 'attachments/{uuid}.pdf'
 * that doesn't match what AttachmentStorage actually writes, so tests that
 * care about the real convention build it by hand (mirrors the idiom at
 * tests/Feature/AttachmentStorageTest.php:168-182).
 */
function plantLocalAttachment(Document $document, string $contents, ?string $filename = null): DocumentAttachment
{
    $path = "attachments/{$document->form_type->value}/{$document->id}/".($filename ?? Str::random(40).'.pdf');
    Storage::disk('local')->put($path, $contents);

    return DocumentAttachment::factory()->local()->create([
        'document_id' => $document->id,
        'path' => $path,
    ]);
}

test('migrates a local-disk row: disk flips, bytes land on supabase, local file is untouched', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'the real file contents');

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('supabase');
    expect(Storage::disk('supabase')->get($attachment->path))->toBe('the real file contents');
    Storage::disk('local')->assertExists($attachment->path);
    expect(Storage::disk('local')->get($attachment->path))->toBe('the real file contents');
});

test('a row already on supabase is left alone', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = DocumentAttachment::factory()->create(['document_id' => $document->id]);

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])
        ->expectsOutputToContain('Nothing to migrate')
        ->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('supabase');
});

test('an orphaned row (local file missing) is skipped, not failed, and disk is left unchanged', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = DocumentAttachment::factory()->local()->create([
        'document_id' => $document->id,
        'path' => "attachments/{$document->form_type->value}/{$document->id}/gone.pdf",
    ]);

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])
        ->expectsOutputToContain('orphaned row')
        ->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('local');
});

test('a differing object already at the same supabase key fails the row and does not clobber the remote bytes', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'the real file');
    Storage::disk('supabase')->put($attachment->path, 'DIFFERENT bytes from a torn prior run');

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])->assertFailed();

    expect($attachment->fresh()->disk)->toBe('local');
    expect(Storage::disk('supabase')->get($attachment->path))->toBe('DIFFERENT bytes from a torn prior run');
    Storage::disk('local')->assertExists($attachment->path);
});

test('--overwrite heals a differing object at the same supabase key', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'the real file');
    Storage::disk('supabase')->put($attachment->path, 'DIFFERENT bytes from a torn prior run');

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true, '--overwrite' => true])->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('supabase');
    expect(Storage::disk('supabase')->get($attachment->path))->toBe('the real file');
});

test('a corrupted read-back after copy fails verification and leaves the row and local file untouched', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'the real file');

    // A double that writes for real (inherits put()/exists()) but returns
    // corrupted bytes on read — simulating a torn upload the initial
    // exists()-based idempotency check wouldn't catch, since the object was
    // never there before this run. getConfig() carries the fake's real
    // 'throw' => true through, so this behaves like production Supabase.
    // IMPORTANT: after the swap, Storage::disk('supabase') returns this
    // double — real stored bytes must be asserted via $fake, captured before
    // the swap.
    $fake = Storage::disk('supabase');
    Storage::set('supabase', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends LocalFilesystemAdapter
    {
        public function get($path)
        {
            return 'corrupted-readback';
        }
    });

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])->assertFailed();

    expect($attachment->fresh()->disk)->toBe('local');
    expect($fake->get($attachment->path))->toBe('the real file');
    Storage::disk('local')->assertExists($attachment->path);
});

test('adopts an already-identical object on supabase without re-uploading', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'identical bytes');
    Storage::disk('supabase')->put($attachment->path, 'identical bytes');

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])
        ->expectsOutputToContain('ADOPTED')
        ->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('supabase');
});

test('one failing row does not stop the rest of the batch', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $good1 = plantLocalAttachment($document, 'good file one', 'a-good-one.pdf');
    $bad = plantLocalAttachment($document, 'this one is bad', 'b-bad.pdf');
    Storage::disk('supabase')->put($bad->path, 'a differing pre-existing object');
    $good2 = plantLocalAttachment($document, 'good file two', 'c-good-two.pdf');

    $this->artisan('attachments:migrate-to-supabase', ['--force' => true])->assertFailed();

    expect($good1->fresh()->disk)->toBe('supabase');
    expect($good2->fresh()->disk)->toBe('supabase');
    expect($bad->fresh()->disk)->toBe('local');
});

test('--dry-run reports what would happen without copying or writing anything', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $contents = 'the exact bytes of this file';
    $attachment = plantLocalAttachment($document, $contents);

    // A single, specific expectation rather than several overlapping weaker
    // ones (id, path, byte count checked separately): when more than one
    // expectsOutputToContain() substring can match the SAME output line,
    // Mockery's expectation director attributes that call to only one of
    // them, so the others report "not found" even though the text is really
    // there. One precise string sidesteps that collision and is a stronger
    // assertion anyway.
    $this->artisan('attachments:migrate-to-supabase', ['--dry-run' => true])
        ->expectsOutputToContain("#{$attachment->id} {$attachment->path}: would migrate (".strlen($contents).' bytes)')
        ->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('local');
    Storage::disk('supabase')->assertMissing($attachment->path);
});

test('declining the confirmation prompt aborts cleanly and changes nothing', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = plantLocalAttachment($document, 'the real file');

    $this->artisan('attachments:migrate-to-supabase')
        ->expectsConfirmation('This copies 1 attachment(s) into Supabase bucket \'test-bucket\' and updates their disk column once each copy is verified. Local files are NOT deleted. Continue?', 'no')
        ->assertSuccessful();

    expect($attachment->fresh()->disk)->toBe('local');
    Storage::disk('supabase')->assertMissing($attachment->path);
});
