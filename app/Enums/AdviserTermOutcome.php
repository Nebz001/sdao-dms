<?php

namespace App\Enums;

/**
 * How an adviser's term ended, kept on the adviser_terms row for the history.
 */
enum AdviserTermOutcome: string
{
    /** Replaced; the account went back to the pool of unassigned advisers. */
    case ReturnedToPool = 'returned_to_pool';

    /** Replaced; the account was deactivated in the same step. */
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::ReturnedToPool => 'Returned to the adviser pool',
            self::Deactivated => 'Account deactivated',
        };
    }
}
