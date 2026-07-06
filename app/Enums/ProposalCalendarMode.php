<?php

namespace App\Enums;

enum ProposalCalendarMode: string
{
    case OnCalendar = 'on_calendar';
    case OffCalendar = 'off_calendar';

    public function label(): string
    {
        return match ($this) {
            ProposalCalendarMode::OnCalendar => 'On Calendar',
            ProposalCalendarMode::OffCalendar => 'Off Calendar',
        };
    }
}
