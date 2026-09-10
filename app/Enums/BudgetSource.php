<?php

namespace App\Enums;

/**
 * "Budget Source" on the Activity Request Form (proposal step 1) — Group C
 * item 2. Was free text; now a closed set of the client's three real
 * options. No "Others" case — none was requested for this field.
 */
enum BudgetSource: string
{
    case RsoFund = 'rso_fund';
    case RsoSavings = 'rso_savings';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::RsoFund => 'RSO Fund',
            self::RsoSavings => 'RSO Savings',
            self::External => 'External',
        };
    }
}
