<?php

namespace App\Dashboard;

use App\Enums\FormType;
use App\Models\Document;
use App\Support\AcademicPeriod;

/**
 * How a document is named on the STUDENT side (an officer's own lists, home
 * cards, history and detail headers), built from the structured fields rather
 * than from `documents.title`, which the submit actions store as
 * "Form type — subject (Org, term)". Three parts, always separate:
 *
 *  - title: the activity's own name for a proposal or report; the form type
 *    itself ("Activity Calendar", "Organization Registration", "Organization
 *    Renewal") for the three forms with no name of their own. Never the
 *    organization, never a dash.
 *  - form type chip: kept for proposals and reports; dropped for the others,
 *    whose title already says it.
 *  - period badge: "1st Term, 2026-2027" for calendars and proposals; the
 *    academic year the document is FOR ("2026-2027") for registrations and
 *    renewals (a renewal filed now covers next year).
 *
 * The stored title is never read, changed or parsed. The admin and approver
 * side keeps DocumentDisplayTitle.
 */
class StudentDocumentLabel
{
    /** @return array<int, string> Relations payload() reads; load them with the query to avoid lazy loads. */
    public static function relations(): array
    {
        return [
            'registrationDetail',
            'activityCalendar',
            'activityProposal.calendarActivity.calendar',
            'afterActivityReport.activityProposal',
        ];
    }

    /**
     * @return array{title: string, showFormType: bool, period: string|null}
     */
    public static function payload(Document $document): array
    {
        return [
            'title' => self::title($document),
            'showFormType' => self::showsFormType($document),
            'period' => self::period($document),
        ];
    }

    public static function title(Document $document): string
    {
        $document->loadMissing(self::relations());

        $name = match ($document->form_type) {
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
            default => null,
        };

        return $name !== null && $name !== '' ? $name : $document->form_type->label();
    }

    /** Proposals and reports are named by their activity, so the form type stays as a chip. */
    public static function showsFormType(Document $document): bool
    {
        return in_array($document->form_type, [FormType::ActivityProposal, FormType::AfterActivityReport], true);
    }

    public static function period(Document $document): ?string
    {
        $document->loadMissing(self::relations());

        return match ($document->form_type) {
            FormType::ActivityCalendar => self::termLabel($document->activityCalendar),
            FormType::ActivityProposal => self::termLabel($document->activityProposal?->calendarActivity?->calendar),
            FormType::OrganizationRegistration => $document->registrationDetail?->covers_academic_year
                ?? AcademicPeriod::forDate($document->created_at)->academicYear,
            FormType::OrganizationRenewal => $document->registrationDetail?->covers_academic_year
                ?? AcademicPeriod::forDate($document->created_at)->nextAcademicYear(),
            FormType::AfterActivityReport => null,
        };
    }

    private static function termLabel(?object $calendar): ?string
    {
        return $calendar === null ? null : "{$calendar->term->label()}, {$calendar->academic_year}";
    }
}
