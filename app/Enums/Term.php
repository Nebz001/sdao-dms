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
}
