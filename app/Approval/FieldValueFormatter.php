<?php

namespace App\Approval;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Collection;

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
        // AsEnumCollection (e.g. CalendarActivity::$sdg) yields a Collection
        // of BackedEnum items, not a plain array — accepted here alongside
        // array so this stays the one formatter for every "list" field,
        // enum-backed or plain strings (e.g. partner_organizations).
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return self::formatScalar($value);
        }

        $parts = [];

        foreach ($value as $item) {
            if ($item instanceof BackedEnum) {
                $text = method_exists($item, 'label') ? $item->label() : (string) $item->value;
            } elseif (is_scalar($item)) {
                $text = trim((string) $item);
            } else {
                continue;
            }

            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Group D item 3: rows are {material, quantity, unit_price}, was
     * {label, amount}. Row total (quantity × unit_price) is computed here,
     * never stored — same precedent as ActivityProposal::expenseItemsTotal.
     */
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

            $material = trim((string) ($row['material'] ?? ''));
            $quantity = $row['quantity'] ?? null;
            $unitPrice = $row['unit_price'] ?? null;

            if ($material === '' && ($quantity === null || $quantity === '') && ($unitPrice === null || $unitPrice === '')) {
                continue;
            }

            if ($quantity === null || $quantity === '' || $unitPrice === null || $unitPrice === '') {
                $parts[] = $material;

                continue;
            }

            $total = (float) $quantity * (float) $unitPrice;
            $parts[] = trim("{$material}: {$quantity} × ₱".number_format((float) $unitPrice, 2).' = ₱'.number_format($total, 2));
        }

        return $parts === [] ? null : implode('; ', $parts);
    }
}
