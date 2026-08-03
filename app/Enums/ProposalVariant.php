<?php

namespace App\Enums;

enum ProposalVariant: string
{
    case RegularOnCalendar = 'regular_on_calendar';
    case RegularOffCalendar = 'regular_off_calendar';
    case ShsOnCalendar = 'shs_on_calendar';
    case ShsOffCalendar = 'shs_off_calendar';

    /**
     * Human-readable label for display (dashboard funnel grouping, etc.),
     * matching the FormType::label()/Role::label() convention.
     */
    public function label(): string
    {
        return match ($this) {
            self::RegularOnCalendar => 'Regular, On-Calendar',
            self::RegularOffCalendar => 'Regular, Off-Calendar',
            self::ShsOnCalendar => 'Senior High School, On-Calendar',
            self::ShsOffCalendar => 'Senior High School, Off-Calendar',
        };
    }
}
