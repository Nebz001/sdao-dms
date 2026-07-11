<?php

namespace App\Approval;

use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;

/**
 * Static registry of flaggable sections per form type (Phase 2 item 9),
 * sourced verbatim from sdao.md's per-form section definitions. Mirrors
 * App\Attachments\AttachmentSlots' pattern — one generic registry reused by
 * every review controller, not five one-offs.
 *
 * Activity Calendar is the one exception: its "sections" are the currently
 * submitted activity rows, which have no stable identity across a
 * return/resubmit cycle (UpdateActivityCalendar deletes and recreates every
 * CalendarActivity row on resubmit). So Calendar has no static list at all —
 * its keys are computed from the document's current row count instead.
 */
class SectionFlags
{
    /**
     * @return array<int, SectionFlag>
     */
    public static function for(FormType $formType): array
    {
        return match ($formType) {
            FormType::OrganizationRegistration, FormType::OrganizationRenewal => [
                new SectionFlag('contact_information', 'Contact Information'),
                new SectionFlag('organization_details', 'Organization Details'),
                new SectionFlag('adviser_selection', 'Adviser Selection'),
                new SectionFlag('attachments', 'Attachments'),
                new SectionFlag('general', 'General'),
            ],
            FormType::ActivityProposal => [
                // Combined/deduped union of the Request Form (step 1) and
                // Narrative (step 2) section lists — confirmed with user.
                // Return-for-revision only ever happens post-submission, when
                // both steps' data already exist on one document, and the
                // resubmit screen (activity-proposals/edit.tsx) already shows
                // both steps' fields combined on one page.
                new SectionFlag('rso_info', 'RSO Info'),
                new SectionFlag('activity_details', 'Activity Details'),
                new SectionFlag('partner_orgs_sdg', 'Partner Orgs & SDG'),
                new SectionFlag('budget', 'Budget'),
                new SectionFlag('schedule_venue', 'Schedule & Venue'),
                new SectionFlag('objectives', 'Objectives'),
                new SectionFlag('activity_description', 'Activity Description'),
                new SectionFlag('resource_person', 'Resource Person'),
                new SectionFlag('general', 'General'),
            ],
            FormType::AfterActivityReport => [
                new SectionFlag('event_details', 'Event Details'),
                new SectionFlag('summary_program', 'Summary/Program'),
                new SectionFlag('evaluation', 'Evaluation'),
                new SectionFlag('attachments', 'Attachments'),
                new SectionFlag('general', 'General'),
            ],
            FormType::ActivityCalendar => [], // dynamic — see calendarKeysFor()
        };
    }

    /**
     * Valid section keys for validation, per form type. Calendar resolves
     * dynamically from the document's current activity rows; every other
     * form type resolves from the static registry above.
     *
     * @return array<int, string>
     */
    public static function validKeysFor(FormType $formType, Document $document): array
    {
        if ($formType === FormType::ActivityCalendar) {
            return self::calendarKeysFor($document);
        }

        return collect(self::for($formType))->pluck('key')->all();
    }

    /**
     * Activity Calendar: keys are "activity_{index}", one per CURRENTLY
     * submitted row — never by CalendarActivity id, since ids aren't stable
     * across a return/resubmit cycle.
     *
     * @return array<int, string>
     */
    public static function calendarKeysFor(Document $document): array
    {
        $count = $document->activityCalendar?->activities()->count() ?? 0;

        if ($count === 0) {
            return [];
        }

        return collect(range(0, $count - 1))->map(fn ($i) => "activity_{$i}")->all();
    }

    /**
     * key => label, for display (Revision History card, etc.). Calendar has
     * no static labels — its history entries resolve to "Activity {n+1}"
     * directly on the frontend instead of a label lookup.
     *
     * @return array<string, string>
     */
    public static function labelsFor(FormType $formType): array
    {
        return collect(self::for($formType))->mapWithKeys(fn (SectionFlag $s) => [$s->key => $s->label])->all();
    }

    /**
     * The sections flagged by the return that put this document in its
     * CURRENT Returned state — i.e. the most recent Returned transition, not
     * a union across every return this document has ever had. Used by every
     * student-facing edit() to drive resubmit-screen highlighting.
     *
     * Document::transitions() carries its own baked-in `orderBy('id')`
     * (ascending, for chronological display elsewhere) — appending
     * `latest('id')` on top would just add a second, ineffective ORDER BY
     * clause behind the first. `reorder()` clears it before sorting
     * descending, so `first()` actually returns the most recent row.
     *
     * @return array<int, string>
     */
    public static function currentlyFlagged(Document $document): array
    {
        return $document->transitions()
            ->where('action', TransitionAction::Returned->value)
            ->reorder('id', 'desc')
            ->first()?->flagged_sections ?? [];
    }
}
