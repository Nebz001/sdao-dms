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

    /**
     * Route name of this form type's student-facing "show" page. Lives on
     * the enum because two callers need the identical mapping
     * (DashboardController's "needs attention" links,
     * DocumentHistoryController's rows) rather than a private const
     * duplicated in both. The approver-facing counterpart stays where it is
     * (DocumentArchiveController::REVIEW_SHOW_ROUTE_NAMES et al.) — a
     * different audience, different authorization.
     */
    public function studentShowRouteName(): string
    {
        return match ($this) {
            self::OrganizationRegistration => 'registrations.show',
            self::OrganizationRenewal => 'renewals.show',
            self::ActivityCalendar => 'activity-calendars.show',
            self::ActivityProposal => 'activity-proposals.show',
            self::AfterActivityReport => 'reports.show',
        };
    }
}
