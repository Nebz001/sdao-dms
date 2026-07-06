<?php

namespace App\Enums;

enum OfficerPosition: string
{
    case President = 'president';
    case Secretary = 'secretary';

    public function label(): string
    {
        return match ($this) {
            self::President => 'President',
            self::Secretary => 'Secretary',
        };
    }
}
