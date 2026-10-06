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
     * form type in a badge of their own (a renewal reads as its coverage, a
     * calendar as its term).
     */
    public static function bareTitle(Document $document): string
    {
        $subject = match ($document->form_type) {
            FormType::OrganizationRegistration => $document->organization?->name,
            FormType::OrganizationRenewal => self::coverageLabel($document->registrationDetail?->covers_academic_year),
            FormType::ActivityCalendar => $document->activityCalendar?->term->label(),
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
        };

        return $subject ?? self::strippedStoredTitle($document);
    }

    /**
     * The document's own title with no form type prefix and no "(Org)" suffix,
     * for rows that already show the form type and organization in their own
     * badge and column. Registration and renewal have no better title than the
     * organization's name; a calendar reads as its term and year.
     */
    public static function subject(Document $document): string
    {
        $subject = match ($document->form_type) {
            FormType::OrganizationRegistration, FormType::OrganizationRenewal => $document->organization?->name,
            FormType::ActivityCalendar => $document->activityCalendar === null
                ? null
                : "{$document->activityCalendar->term->label()}, {$document->activityCalendar->academic_year}",
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
        };

        return $subject ?? self::strippedStoredTitle($document);
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
