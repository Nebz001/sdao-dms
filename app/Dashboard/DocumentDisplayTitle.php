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
        $subject = match ($document->form_type) {
            FormType::OrganizationRegistration => $document->organization?->name,
            FormType::OrganizationRenewal => self::coverageLabel($document->registrationDetail?->covers_academic_year),
            FormType::ActivityCalendar => $document->activityCalendar?->term->label(),
            FormType::ActivityProposal => $document->activityProposal?->title,
            FormType::AfterActivityReport => $document->afterActivityReport?->activityProposal?->title,
        };

        return $document->form_type->label().': '.($subject ?? self::strippedStoredTitle($document));
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
