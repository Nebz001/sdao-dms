<?php

namespace App\Printing;

use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Support\DisplayTimezone;

/**
 * Printable rendering of the Activity Calendar submission as a landscape PDF
 * (Phase 2 remediation, Part 2). The reference `forms/calendar-of-activities-sample.xlsx`
 * is SDAO's own aggregate master log spanning many organizations' activities
 * across many rows — this print artifact is the per-Document submission form
 * for a SINGLE organization's calendar, so RSO Name is identical on every
 * row here. That is expected, not a bug: reproducing the master log itself
 * is out of scope (there is no "master log" Document to print).
 *
 * A true .xlsx export was considered and rejected for this pass: it needs a
 * new dependency (phpoffice/phpspreadsheet, not installed) and controller
 * branching on content type, both of which the "one class, one match arm,
 * one Blade view, no route/controller/policy changes" constraint rules out.
 * Landscape PDF reproduces the source's column order/layout without either.
 */
class ActivityCalendarForm implements PrintableForm
{
    /**
     * Timestamps are stored/cast in UTC; the office and every approver are
     * Asia/Manila. See OrganizationApplicationForm's identical constant.
     */
    private const string DISPLAY_TZ = DisplayTimezone::ASIA_MANILA;

    public function view(): string
    {
        return 'print.activity-calendar';
    }

    public function paper(): string
    {
        return 'a4';
    }

    public function orientation(): string
    {
        return 'landscape';
    }

    public function filename(Document $document): string
    {
        $slug = str($document->organization->name)->slug();

        return "activity-calendar-{$slug}-{$document->id}";
    }

    public function eagerLoads(): array
    {
        return [
            'organization',
            'activityCalendar.activities',
            'transitions',
        ];
    }

    public function data(Document $document): array
    {
        $calendar = $document->activityCalendar;

        // Mirrors OrganizationApplicationForm's null-detail guard: a
        // supported-form-type Document with no backing ActivityCalendar row
        // is a data-integrity problem, not a normal state the print route
        // should render — fail as a 404, not a fatal error below.
        if ($calendar === null) {
            abort(404);
        }

        $organization = $document->organization;
        $status = $this->statusLabel($document->status);
        $dateReceived = $this->dateReceived($document);

        return [
            'title' => "CALENDAR OF ACTIVITIES AY. {$calendar->academic_year} ({$calendar->term->label()})",
            'rows' => $calendar->activities->map(fn ($activity) => [
                'rso_name' => $organization->name,
                'date' => $activity->activity_date->format('m/d/Y'),
                'activity_name' => $activity->name,
                'sdg' => $activity->sdg !== null ? "SDG {$activity->sdg->number()} — {$activity->sdg->label()}" : null,
                'venue' => $activity->venue,
                'participant_program_assigned' => $activity->participant_program_assigned,
                'budget' => $activity->budget !== null ? number_format((float) $activity->budget, 2) : null,
                'status' => $status,
                // Header kept misspelled verbatim ("DATE RECIEVED") to match
                // the source spreadsheet — same precedent as
                // OrganizationApplicationForm's verbatim duplicated checklist
                // row; see the Blade view for the literal header text.
                'date_received' => $dateReceived,
            ])->all(),
        ];
    }

    private function statusLabel(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::Draft => 'Draft',
            DocumentStatus::InReview => 'In Review',
            DocumentStatus::Returned => 'Returned',
            DocumentStatus::Approved => 'Approved',
            DocumentStatus::Rejected => 'Rejected',
        };
    }

    /**
     * Same transitions-based derivation as AfterActivityReportForm's Date
     * Submitted — there is no submitted_at column, and the latest
     * submitted/resubmitted transition reflects the version that actually
     * went through, not an earlier draft.
     */
    private function dateReceived(Document $document): ?string
    {
        $transition = $document->transitions
            ->filter(fn ($t) => in_array($t->action, [TransitionAction::Submitted, TransitionAction::Resubmitted], true))
            ->sortBy('created_at')
            ->last();

        return $transition?->created_at->setTimezone(self::DISPLAY_TZ)->format('m/d/Y');
    }
}
