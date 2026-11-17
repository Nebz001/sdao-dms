<?php

use App\Support\SpecificObjectives;

/**
 * Specific Objectives is plain text, one objective per line, numbered only
 * when shown. App\Support\SpecificObjectives is the single splitter behind the
 * print form, the mobile resource and the validation rule.
 */
dataset('objective texts', [
    'one per line' => ["Raise awareness\nTrain members", ['Raise awareness', 'Train members']],
    'blank lines dropped' => ["First\n\n\nSecond\n   \nThird", ['First', 'Second', 'Third']],
    'every line trimmed' => ["   padded   \n\t tabbed\t", ['padded', 'tabbed']],
    'author order kept, not sorted' => ["Zebra\nApple\nMango", ['Zebra', 'Apple', 'Mango']],
    'windows and old mac newlines' => ["One\r\nTwo\rThree", ['One', 'Two', 'Three']],
    'typed numbers are not doubled' => ["1. Raise awareness\n2) Train members", ['Raise awareness', 'Train members']],
    'typed bullets are not doubled' => ["- Raise awareness\n• Train members\n* Share results", ['Raise awareness', 'Train members', 'Share results']],
    'a number inside a sentence is kept' => ["2026 targets are set\nReach 3. goals", ['2026 targets are set', 'Reach 3. goals']],
    'a lone dash is still an objective' => ['-', ['-']],
    'null' => [null, []],
    'empty' => ['', []],
    'only whitespace' => ["  \n\t\n  ", []],
]);

test('split trims, drops blank lines and keeps the order', function (?string $text, array $expected) {
    expect(SpecificObjectives::split($text))->toBe($expected);
})->with('objective texts');

test('hasAny is true only when at least one line has content', function () {
    expect(SpecificObjectives::hasAny("  \n "))->toBeFalse()
        ->and(SpecificObjectives::hasAny(null))->toBeFalse()
        ->and(SpecificObjectives::hasAny("\nOne\n"))->toBeTrue();
});

test('normalize turns every line break into a plain newline', function () {
    expect(SpecificObjectives::normalize("One\r\nTwo\rThree\nFour"))->toBe("One\nTwo\nThree\nFour");
});
