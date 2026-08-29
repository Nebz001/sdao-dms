<?php

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;

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
        expect($slot['max_kb'])->toBe(10240);
    }
});

test('slotsFor() exposes the photo max_kb for a multi-file slot', function () {
    $slots = AttachmentSlots::slotsFor(FormType::AfterActivityReport);
    $photos = collect($slots)->firstWhere('key', 'photos');

    expect($photos)->not->toBeNull();
    expect($photos['multiple'])->toBeTrue();
    expect($photos['max_kb'])->toBe(5120);
});
