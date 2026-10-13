<?php

namespace App\Dashboard;

use App\Enums\FormType;
use App\Models\Document;

/**
 * "{Form type}: {subject}" for a document, built from its source models
 * rather than by parsing `documents.title`, which carries an em dash and the
 * organization's name baked in (e.g. "Activity Proposal — Foo (Some Org)").
 * Used by every dashboard row that names a document, so the same document
 * always reads the same way across cards.
 */
class DocumentDisplayTitle
{
    /**
     * Relations to eager-load on a document query so for() never lazy-loads.
     *
     * @return array<int, string>
     */
    public static function relations(): array
    {
        return ['organization', 'registrationDetail', 'activityCalendar', 'activityProposal', 'afterActivityReport.activityProposal'];
    }

    public static function for(Document $document): string
    {
        return $document->form_type->label().': '.self::bareTitle($document);
    }

    /**
     * for() without the "{Form type}: " prefix, for rows that already show the
     * form type in a badge of their own (a renewal reads as its term, a
     * calendar as its term).
     */
    public static function bareTitle(Document $document): string
    {
        $subject = match ($document->form_type) {
            FormType::OrganizationRegistration => $document->organization?->name,
            FormType::OrganizationRenewal => $document->registrationDetail?->period()?->label(),
            FormType::ActivityCalendar => $document->activityCalendar?->term->label(),
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
        };

        return $subject ?? self::strippedStoredTitle($document);
    }

    /**
     * The document's own title with no form type prefix and no "(Org)" suffix,
     * for rows that already show the form type and organization in their own
     * badge and column. A registration has no better title than the
     * organization's name; a calendar reads as its term and year; a renewal is
     * just "Organization Renewal", with its term shown by the row's term
     * badge (see termLabel()) — never the stored "Form — Org (Term)" string.
     */
    public static function subject(Document $document): string
    {
        $subject = match ($document->form_type) {
            FormType::OrganizationRegistration => $document->organization?->name,
            FormType::OrganizationRenewal => $document->form_type->label(),
            FormType::ActivityCalendar => $document->activityCalendar === null
                ? null
                : "{$document->activityCalendar->term->label()}, {$document->activityCalendar->academic_year}",
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
        };

        return $subject ?? self::strippedStoredTitle($document);
    }

    /**
     * The term a renewal was filed for ("2nd Term, 2026-2027"), for the term
     * badge beside the organization and form type badges. Null for every other
     * form type, whose own title or period already says it.
     */
    public static function termLabel(Document $document): ?string
    {
        if ($document->form_type !== FormType::OrganizationRenewal) {
            return null;
        }

        return $document->registrationDetail?->period()?->label();
    }

    /** The approver-facing show page for any document that has ever been submitted. */
    public static function href(Document $document): string
    {
        return route('review.'.$document->form_type->studentShowRouteName(), $document);
    }

    /** "2026-2027" → "2026 to 2027". */
    public static function coverageLabel(?string $academicYear): ?string
    {
        return $academicYear === null ? null : str_replace('-', ' to ', $academicYear);
    }

    /**
     * Fallback when the source row is missing: drops the "Form — " prefix
     * and the trailing "(…)" the submit actions bake into the stored title.
     */
    private static function strippedStoredTitle(Document $document): string
    {
        $title = (string) preg_replace('/^[^—]*—\s*/u', '', $document->title);

        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $title));
    }
}
