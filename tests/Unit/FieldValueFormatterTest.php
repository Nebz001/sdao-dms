<?php

use App\Approval\FieldValueFormatter;
use App\Enums\OrganizationType;
use Illuminate\Support\Carbon;

test('null, empty string and empty array all format to null', function () {
    expect(FieldValueFormatter::format(null))->toBeNull();
    expect(FieldValueFormatter::format(''))->toBeNull();
    expect(FieldValueFormatter::format([], 'list'))->toBeNull();
    expect(FieldValueFormatter::format('   '))->toBeNull();
});

test('zero is a real value, not an empty one', function () {
    expect(FieldValueFormatter::format(0))->toBe('0');
    expect(FieldValueFormatter::format('0'))->toBe('0');
});

test('backed enums render through label(), not ->value', function () {
    expect(FieldValueFormatter::format(OrganizationType::CoCurricular))
        ->toBe(OrganizationType::CoCurricular->label());
});

test('dates render human-readable', function () {
    expect(FieldValueFormatter::format(Carbon::parse('2026-09-15')))->toBe('Sep 15, 2026');
});

test('booleans render as Yes/No', function () {
    expect(FieldValueFormatter::format(true))->toBe('Yes');
    expect(FieldValueFormatter::format(false))->toBe('No');
});

test('money formats a decimal:2 cast string with the peso prefix', function () {
    // decimal:2 hands back a STRING, not a float — the real shape a model
    // attribute arrives in.
    expect(FieldValueFormatter::format('12500.00', 'money'))->toBe('₱12,500.00');
    expect(FieldValueFormatter::format(1234.5, 'money'))->toBe('₱1,234.50');
});

test('lists join with commas and drop empty entries', function () {
    expect(FieldValueFormatter::format(['Alpha', '', 'Beta'], 'list'))->toBe('Alpha, Beta');
    expect(FieldValueFormatter::format(['', '  '], 'list'))->toBeNull();
});

test('expense items render as "material: qty × unit price = total", semicolon-joined', function () {
    expect(FieldValueFormatter::format([
        ['material' => 'Venue rental', 'quantity' => '1', 'unit_price' => '5000'],
        ['material' => 'Snacks', 'quantity' => '2.5', 'unit_price' => '1000'],
    ], 'expense_items'))->toBe('Venue rental: 1 × ₱5,000.00 = ₱5,000.00; Snacks: 2.5 × ₱1,000.00 = ₱2,500.00');
});

test('expense items with no rows format to null', function () {
    expect(FieldValueFormatter::format([], 'expense_items'))->toBeNull();
    expect(FieldValueFormatter::format([['material' => '', 'quantity' => null, 'unit_price' => null]], 'expense_items'))->toBeNull();
});
