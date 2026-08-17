<?php

namespace App\Approval;

use BackedEnum;
use DateTimeInterface;

/**
 * Turns a raw Eloquent cast value into the display-ready string frozen onto
 * a Resubmitted transition's field_changes payload (see FieldChangeSet).
 *
 * Pure: no DB, no models, no form types. That's deliberate — it's the one
 * piece of this feature with real branching and zero context, so it gets its
 * own DB-free tests/Unit suite (tests/Pest.php scopes RefreshDatabase and
 * Storage::fake() to Feature only).
 *
 * NOTE on the ₱ prefix: elsewhere in the app PHP does number_format() and the
 * template adds the currency symbol (see ActivityProposal::expenseItemsTotal).
 * Here the stored value IS the final display string — field_changes is a
 * frozen human-readable snapshot, not raw data to be re-decorated later — so
 * the symbol is baked in here and the shared React component stays fully
 * form-agnostic.
 */
final class FieldValueFormatter
{
    public static function format(mixed $value, string $type = 'text'): ?string
    {
        // Strict comparisons only: integer 0 and string "0" are real values,
        // not "nothing was entered".
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $formatted = match ($type) {
            'money' => '₱'.number_format((float) $value, 2),
            'list' => self::formatList($value),
            'expense_items' => self::formatExpenseItems($value),
            default => self::formatScalar($value),
        };

        return ($formatted === null || trim($formatted) === '') ? null : $formatted;
    }

    private static function formatScalar(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            // Every enum in App\Enums that reaches this path defines
            // label(); the fallback keeps an unlabeled enum from fataling.
            return method_exists($value, 'label') ? $value->label() : (string) $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('M j, Y');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return self::formatList($value);
        }

        return trim((string) $value);
    }

    private static function formatList(mixed $value): ?string
    {
        if (! is_array($value)) {
            return self::formatScalar($value);
        }

        $parts = [];

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $text = trim((string) $item);

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function formatExpenseItems(mixed $value): ?string
    {
        if (! is_array($value)) {
            return null;
        }

        $parts = [];

        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $amount = $row['amount'] ?? null;

            if ($label === '' && ($amount === null || $amount === '')) {
                continue;
            }

            $parts[] = ($amount === null || $amount === '')
                ? $label
                : trim($label.': ₱'.number_format((float) $amount, 2));
        }

        return $parts === [] ? null : implode('; ', $parts);
    }
}
