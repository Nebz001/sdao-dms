<?php

namespace App\Enums;

enum ProposalVariant: string
{
    case RegularOnCalendar = 'regular_on_calendar';
    case RegularOffCalendar = 'regular_off_calendar';
    case ShsOnCalendar = 'shs_on_calendar';
    case ShsOffCalendar = 'shs_off_calendar';
}
