<?php

namespace App\Enums;

enum Term: string
{
    case FirstTerm = 'first_term';
    case SecondTerm = 'second_term';
    case ThirdTerm = 'third_term';

    public function label(): string
    {
        return match ($this) {
            Term::FirstTerm => '1st Term',
            Term::SecondTerm => '2nd Term',
            Term::ThirdTerm => '3rd Term',
        };
    }

    /**
     * Ordinal position within an academic year (1-3). The comparison
     * primitive AcademicPeriod builds year+term ordering on.
     */
    public function order(): int
    {
        return match ($this) {
            Term::FirstTerm => 1,
            Term::SecondTerm => 2,
            Term::ThirdTerm => 3,
        };
    }

    /**
     * The term that follows this one, wrapping 3rd back to 1st (of the next
     * academic year — AcademicPeriod::next() owns the year increment).
     */
    public function next(): self
    {
        return match ($this) {
            Term::FirstTerm => Term::SecondTerm,
            Term::SecondTerm => Term::ThirdTerm,
            Term::ThirdTerm => Term::FirstTerm,
        };
    }
}
