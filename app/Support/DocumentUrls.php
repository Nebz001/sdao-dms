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
 *
 * Absolute vs relative is NOT a style choice here — the two audiences have
 * opposite requirements, and getting it backwards breaks one of them:
 *
 * - Mail MUST be absolute. A link in an email has no page to be relative to.
 * - The notification bell MUST be relative. Its payload is written once (often
 *   from the CLI or a queue worker, where `route()` falls back to APP_URL) and
 *   clicked later from whatever origin the user actually browses. An absolute
 *   URL baked at write time is a cross-origin URL the moment those differ —
 *   e.g. stored `http://localhost:8000/...` clicked from `http://127.0.0.1:8000`
 *   — and Inertia issues visits as XHR with no same-origin escape hatch
 *   (`hrefToUrl()` is just `new URL(href, window.location)`), so the browser
 *   CORS-blocks it and the click silently does nothing. Path-only URLs are
 *   origin-agnostic and sidestep that entirely.
 *
 * Both variants resolve through the same route-name maps below, so the two
 * audiences can never drift to different pages.
 */
class DocumentUrls
{
    /**
     * Route name per form type for the approver-facing review screen.
     *
     * @return non-empty-string
     */
    private static function reviewerRouteName(FormType $formType): string
    {
        return match ($formType) {
            FormType::OrganizationRegistration => 'review.registrations.show',
            FormType::OrganizationRenewal => 'review.renewals.show',
            FormType::ActivityCalendar => 'review.activity-calendars.show',
            FormType::ActivityProposal => 'review.activity-proposals.show',
            FormType::AfterActivityReport => 'review.reports.show',
        };
    }

    /**
     * Route name per form type for the submitter/officer-facing screen.
     *
     * @return non-empty-string
     */
    private static function submitterRouteName(FormType $formType): string
    {
        return match ($formType) {
            FormType::OrganizationRegistration => 'registrations.show',
            FormType::OrganizationRenewal => 'renewals.show',
            FormType::ActivityCalendar => 'activity-calendars.show',
            FormType::ActivityProposal => 'activity-proposals.show',
            FormType::AfterActivityReport => 'reports.show',
        };
    }

    /** Absolute URL where an approver reviews the document — for mail only. */
    public static function forReviewer(Document $document): string
    {
        return route(self::reviewerRouteName($document->form_type), $document);
    }

    /** Absolute URL where the submitter/officers view the document — for mail only. */
    public static function forSubmitter(Document $document): string
    {
        return route(self::submitterRouteName($document->form_type), $document);
    }

    /** Origin-relative path to the approver's review screen — for the bell. */
    public static function pathForReviewer(Document $document): string
    {
        return route(self::reviewerRouteName($document->form_type), $document, absolute: false);
    }

    /** Origin-relative path to the submitter-facing screen — for the bell. */
    public static function pathForSubmitter(Document $document): string
    {
        return route(self::submitterRouteName($document->form_type), $document, absolute: false);
    }
}
