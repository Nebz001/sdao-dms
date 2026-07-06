<?php

namespace App\Enums;

enum FormType: string
{
    case OrganizationRegistration = 'organization_registration';
    case OrganizationRenewal = 'organization_renewal';
    case ActivityCalendar = 'activity_calendar';
    case ActivityProposal = 'activity_proposal';
    case AfterActivityReport = 'after_activity_report';
}
