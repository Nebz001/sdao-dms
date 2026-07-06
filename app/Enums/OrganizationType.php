<?php

namespace App\Enums;

enum OrganizationType: string
{
    case CoCurricular = 'co_curricular';
    case ExtraCurricular = 'extra_curricular';

    public function label(): string
    {
        return match ($this) {
            self::CoCurricular => 'Co-curricular',
            self::ExtraCurricular => 'Extra-curricular',
        };
    }
}
