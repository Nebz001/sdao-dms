<?php

use App\Attachments\AttachmentStorage;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 item 8 — unit-level coverage of the generic attachment storage
 * service shared by every form type's write path.
 */
beforeEach(function () {
    $this->storage = app(AttachmentStorage::class);
    $this->actor = User::factory()->create();
});

test('store() writes the file to disk and creates a row', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $file = UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf');

    $attachment = $this->storage->store($document, 'letter_of_intent', $file, $this->actor, multiple: false);

    expect($attachment->slot_key)->toBe('letter_of_intent');
    expect($attachment->original_filename)->toBe('letter.pdf');
    expect($attachment->uploaded_by)->toBe($this->actor->id);
    Storage::disk('local')->assertExists($attachment->path);
});

test('store() on a single-file slot replaces the previous file, deleting it from disk', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);

    $first = $this->storage->store(
        $document, 'letter_of_intent', UploadedFile::fake()->create('first.pdf', 50, 'application/pdf'), $this->actor, multiple: false,
    );
    $firstPath = $first->path;

    $second = $this->storage->store(
        $document, 'letter_of_intent', UploadedFile::fake()->create('second.pdf', 50, 'application/pdf'), $this->actor, multiple: false,
    );

    expect(DocumentAttachment::where('document_id', $document->id)->where('slot_key', 'letter_of_intent')->count())->toBe(1);
    expect(DocumentAttachment::find($second->id)->original_filename)->toBe('second.pdf');
    Storage::disk('local')->assertMissing($firstPath);
});

test('store() on a multi-file slot accumulates rather than replacing', function () {
    $document = Document::factory()->create(['form_type' => FormType::AfterActivityReport]);

    $this->storage->store($document, 'photos', UploadedFile::fake()->create('photo-1.jpg', 100, 'image/jpeg'), $this->actor, multiple: true);
    $this->storage->store($document, 'photos', UploadedFile::fake()->create('photo-2.jpg', 100, 'image/jpeg'), $this->actor, multiple: true);

    expect(DocumentAttachment::where('document_id', $document->id)->where('slot_key', 'photos')->count())->toBe(2);
});

test('storeMany() stores every slot in the given array', function () {
    $document = Document::factory()->create(['form_type' => FormType::AfterActivityReport]);

    $this->storage->storeMany($document, [
        'photos' => [UploadedFile::fake()->create('photo-1.jpg', 100, 'image/jpeg')],
        'evaluation_form' => UploadedFile::fake()->create('eval.pdf', 100, 'application/pdf'),
        'attendance_sheet' => UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
    ], $this->actor);

    expect($document->attachments()->pluck('slot_key')->sort()->values()->all())
        ->toBe(['attendance_sheet', 'evaluation_form', 'photos']);
});

test('storeMany() ignores an unknown slot key for the document\'s form type', function () {
    $document = Document::factory()->create(['form_type' => FormType::AfterActivityReport]);

    $this->storage->storeMany($document, [
        'not_a_real_slot' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ], $this->actor);

    expect($document->attachments()->count())->toBe(0);
});

test('assertRequiredSlotsFilled() throws naming every missing required slot label', function () {
    $document = Document::factory()->create(['form_type' => FormType::AfterActivityReport]);

    expect(fn () => $this->storage->assertRequiredSlotsFilled($document))
        ->toThrow(ValidationException::class);

    try {
        $this->storage->assertRequiredSlotsFilled($document);
    } catch (ValidationException $e) {
        $message = $e->errors()['attachments'][0];
        expect($message)->toContain('Photos');
        expect($message)->toContain('Sample Evaluation Form');
        expect($message)->toContain('Attendance Sheet');
    }
});

test('assertRequiredSlotsFilled() passes once every required slot has a file', function () {
    $document = Document::factory()->create(['form_type' => FormType::AfterActivityReport]);

    $this->storage->storeMany($document, [
        'photos' => [UploadedFile::fake()->create('photo-1.jpg', 100, 'image/jpeg')],
        'evaluation_form' => UploadedFile::fake()->create('eval.pdf', 100, 'application/pdf'),
        'attendance_sheet' => UploadedFile::fake()->create('attendance.pdf', 100, 'application/pdf'),
    ], $this->actor);

    $this->storage->assertRequiredSlotsFilled($document);

    expect(true)->toBeTrue(); // reached without throwing
});

test('assertRequiredSlotsFilled() ignores the optional slot entirely', function () {
    $document = Document::factory()->create(['form_type' => FormType::ActivityProposal]);

    $this->storage->assertRequiredSlotsFilled($document);

    expect(true)->toBeTrue(); // resume_of_resource_person is optional — no exception even with zero attachments
});

test('delete() removes the row and the file from disk', function () {
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration]);
    $attachment = $this->storage->store(
        $document, 'letter_of_intent', UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf'), $this->actor, multiple: false,
    );
    $path = $attachment->path;

    $this->storage->delete($attachment);

    expect(DocumentAttachment::find($attachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});
