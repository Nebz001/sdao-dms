<?php

namespace App\Printing;

use App\Enums\FormType;

/**
 * Static registry of printable-form implementations per form type — mirrors
 * App\Attachments\AttachmentSlots::for(). Organization Registration/Renewal
 * is the pilot; the remaining three form types (Part 2) are wired here, each
 * with zero route/controller/policy changes required.
 */
class PrintableForms
{
    public static function for(FormType $formType): ?PrintableForm
    {
        return match ($formType) {
            FormType::OrganizationRegistration,
            FormType::OrganizationRenewal => new OrganizationApplicationForm,
            // Needs RoleDirectory autowired — resolved via the container
            // rather than manually instantiated.
            FormType::ActivityProposal => app(ActivityProposalForm::class),
            FormType::AfterActivityReport => new AfterActivityReportForm,
            FormType::ActivityCalendar => new ActivityCalendarForm,
        };
    }
}
