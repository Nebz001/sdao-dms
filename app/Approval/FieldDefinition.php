<?php

namespace App\Approval;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A single trackable field inside a flaggable section (field-level revision
 * diffs) — e.g. "Venue" inside "Schedule & Venue". Field lists are static PHP
 * (see SectionFields), mirroring App\Approval\SectionFlag's shape exactly.
 *
 * $type selects a formatter branch in FieldValueFormatter:
 *   'text'          — default; scalars, BackedEnum (via ->label()), dates, bools
 *   'money'         — "₱1,234.56"
 *   'list'          — array of strings, joined with ", "
 *   'expense_items' — array of {label, amount} rows, joined with "; "
 *
 * $using is an optional resolver for the rare field whose display value is
 * NOT the column itself — currently only adviser_id, which is meaningless as
 * a raw id ("12 → 15") and is resolved to the adviser's name instead. Kept as
 * a closure rather than a special-cased type so FieldValueFormatter stays
 * pure and DB-free.
 */
final readonly class FieldDefinition
{
    /** @param  (Closure(Model): mixed)|null  $using */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public ?Closure $using = null,
    ) {}
}
