<?php

namespace App\Enums;

enum RenewalEligibility: string
{
    case Eligible = 'eligible';
    case NoPriorRecord = 'no_prior_record';
    case NotYetDue = 'not_yet_due';
    case AlreadyFiledThisTerm = 'already_filed';

    public function label(): string
    {
        return match ($this) {
            self::Eligible => 'Eligible',
            self::NoPriorRecord => 'No prior approved record',
            self::NotYetDue => 'Not yet due',
            self::AlreadyFiledThisTerm => 'Already filed this term',
        };
    }
}
