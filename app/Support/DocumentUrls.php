<?php

namespace App\Support;

use App\Enums\FormType;
use App\Models\Document;

/**
 * Single source of truth for "where does this document link to" — both
 * per-form URL variants (reviewer vs submitter) were previously duplicated
 * as an identical `match ($document->form_type)` inside ApproverHandOffMail
 * and DocumentOutcomeMail. Now shared by those Mailables AND the
 * notification database payloads that link a bell item back to the page,
 * so the mail and in-app copies of a notification can never disagree about
 * where they point.
 */
class DocumentUrls
{
    /** Where an approver reviews the document (the `review.*.show` routes). */
    public static function forReviewer(Document $document): string
    {
        return match ($document->form_type) {
            FormType::OrganizationRegistration => route('review.registrations.show', $document),
            FormType::OrganizationRenewal => route('review.renewals.show', $document),
            FormType::ActivityCalendar => route('review.activity-calendars.show', $document),
            FormType::ActivityProposal => route('review.activity-proposals.show', $document),
            FormType::AfterActivityReport => route('review.reports.show', $document),
        };
    }

    /** Where the submitter/officers view the document (the `*.show` routes). */
    public static function forSubmitter(Document $document): string
    {
        return match ($document->form_type) {
            FormType::OrganizationRegistration => route('registrations.show', $document),
            FormType::OrganizationRenewal => route('renewals.show', $document),
            FormType::ActivityCalendar => route('activity-calendars.show', $document),
            FormType::ActivityProposal => route('activity-proposals.show', $document),
            FormType::AfterActivityReport => route('reports.show', $document),
        };
    }
}
