<?php

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Support\UploadLimits;

/*
 * A large file used to only be rejected server-side, after a full upload
 * round trip — slotsFor() exposes each slot's own byte limit so the frontend
 * can reject it instantly at selection time instead (see
 * attachment-slot-field.tsx). This pins that the exposed limit actually
 * matches the limit validationRules() enforces, so the two can never drift.
 */
test('slotsFor() exposes the document max_kb for a single-file slot', function () {
    $slots = AttachmentSlots::slotsFor(FormType::OrganizationRegistration);

    expect($slots)->not->toBeEmpty();

    foreach ($slots as $slot) {
        expect($slot['multiple'])->toBeFalse();
        expect($slot['max_kb'])->toBe(UploadLimits::MAX_FILE_KB);
    }
});

test('slotsFor() exposes the photo max_kb for a multi-file slot', function () {
    $slots = AttachmentSlots::slotsFor(FormType::AfterActivityReport);
    $photos = collect($slots)->firstWhere('key', 'photos');

    expect($photos)->not->toBeNull();
    expect($photos['multiple'])->toBeTrue();
    expect($photos['max_kb'])->toBe(UploadLimits::MAX_FILE_KB);
});

test('the shared upload ceiling is 2 MB and every slot rule enforces it', function () {
    expect(UploadLimits::MAX_FILE_KB)->toBe(2048);
    expect(UploadLimits::fileTooLargeMessage())->toBe('File is too large. Maximum size is 2 MB.');

    foreach (FormType::cases() as $formType) {
        foreach (AttachmentSlots::validationRules($formType, requiredAtWrite: true) as $key => $rules) {
            if (str_ends_with($key, '.*') || ! collect($rules)->contains(fn ($rule) => is_string($rule) && str_starts_with($rule, 'mimes:'))) {
                continue;
            }

            expect($rules)->toContain('max:2048');
        }
    }
});

test('every slot has the shared too-large message', function () {
    $messages = AttachmentSlots::validationMessages(FormType::AfterActivityReport);

    expect($messages)->toHaveKey('attachments.photos.*.max', 'File is too large. Maximum size is 2 MB.');
    expect($messages['attachments.photos.*.max'])->toBe(UploadLimits::fileTooLargeMessage());
});
