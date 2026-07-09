<?php

namespace App\Enums;

enum FormType: string
{
    case OrganizationRegistration = 'organization_registration';
    case OrganizationRenewal = 'organization_renewal';
    case ActivityCalendar = 'activity_calendar';
    case ActivityProposal = 'activity_proposal';
    case AfterActivityReport = 'after_activity_report';

    /**
     * Human-readable label for display (email subjects/bodies, badges, etc).
     */
    public function label(): string
    {
        return match ($this) {
            self::OrganizationRegistration => 'Organization Registration',
            self::OrganizationRenewal => 'Organization Renewal',
            self::ActivityCalendar => 'Activity Calendar',
            self::ActivityProposal => 'Activity Proposal',
            self::AfterActivityReport => 'After-Activity Report',
        };
    }
}
