<?php

namespace App\Enums;

enum ScopeType: string
{
    case Organization = 'organization';
    case Program = 'program';
    case School = 'school';
    case Global = 'global';
}
