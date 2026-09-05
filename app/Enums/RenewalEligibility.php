<?php

namespace App\Enums;

enum RenewalEligibility: string
{
    case Eligible = 'eligible';
    case NoPriorRecord = 'no_prior_record';
    case SeasonClosed = 'season_closed';
    case NotYetDue = 'not_yet_due';
    case AlreadyFiledThisYear = 'already_filed';

    public function label(): string
    {
        return match ($this) {
            self::Eligible => 'Eligible',
            self::NoPriorRecord => 'No prior approved record',
            self::SeasonClosed => 'Renewal season closed',
            self::NotYetDue => 'Not yet due',
            self::AlreadyFiledThisYear => 'Already filed this year',
        };
    }
}
