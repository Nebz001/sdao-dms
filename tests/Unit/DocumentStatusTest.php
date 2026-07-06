<?php

use App\Enums\DocumentStatus;

test('Approved and Rejected are terminal statuses', function () {
    expect(DocumentStatus::Approved->isTerminal())->toBeTrue();
    expect(DocumentStatus::Rejected->isTerminal())->toBeTrue();
});

test('Draft, InReview, and Returned are non-terminal statuses', function () {
    expect(DocumentStatus::Draft->isTerminal())->toBeFalse();
    expect(DocumentStatus::InReview->isTerminal())->toBeFalse();
    expect(DocumentStatus::Returned->isTerminal())->toBeFalse();
});
