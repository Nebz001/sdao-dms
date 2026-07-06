<?php

use App\Enums\OfficerPosition;

test('OfficerPosition has President and Secretary cases', function () {
    expect(OfficerPosition::cases())->toHaveCount(2);
    expect(OfficerPosition::President->value)->toBe('president');
    expect(OfficerPosition::Secretary->value)->toBe('secretary');
});

test('OfficerPosition label returns human-readable string', function () {
    expect(OfficerPosition::President->label())->toBe('President');
    expect(OfficerPosition::Secretary->label())->toBe('Secretary');
});

test('OfficerPosition can be instantiated from string', function () {
    expect(OfficerPosition::from('president'))->toBe(OfficerPosition::President);
    expect(OfficerPosition::from('secretary'))->toBe(OfficerPosition::Secretary);
});
