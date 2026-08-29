<?php

namespace App\Printing;

use App\Enums\TransitionAction;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Support\DisplayTimezone;
use Carbon\Carbon;

/**
 * Printable rendering of NU Lipa's "After Activity Report" template
 * (After-activity-form-template-1.docx) — Phase 2 remediation, Part 2.
 *
 * No signature/approval section exists anywhere on this template, so no
 * SignatureBlock usage here (unlike ActivityProposalForm). Sections are
 * prose labels, not checklists — only "attachment present/absent" is
 * derivable for Photos/Evaluation Form/Attendance Sheet.
 */
class AfterActivityReportForm implements PrintableForm
{
    /**
     * Timestamps are stored/cast in UTC; the office and every approver are
     * Asia/Manila. See OrganizationApplicationForm's identical constant.
     */
    private const string DISPLAY_TZ = DisplayTimezone::ASIA_MANILA;

    public function view(): string
    {
        return 'print.after-activity-report';
    }

    public function paper(): string
    {
        // The source docx is Letter-sized (12240x15840 twips); no page split
        // needed, so this is the only Part 2 form printed at its native size.
        return 'letter';
    }

    public function orientation(): string
    {
        return 'portrait';
    }

    public function filename(Document $document): string
    {
        $slug = str($document->organization->name)->slug();

        return "after-activity-report-{$slug}-{$document->id}";
    }

    public function eagerLoads(): array
    {
        return [
            'afterActivityReport.activityProposal.calendarActivity',
            'transitions',
            'attachments',
        ];
    }

    public function data(Document $document): array
    {
        $report = $document->afterActivityReport;

        // Mirrors OrganizationApplicationForm's null-detail guard: a
        // supported-form-type Document with no backing AfterActivityReport
        // row is a data-integrity problem, not a normal state the print
        // route should render — fail as a 404, not a fatal error below.
        if ($report === null) {
            abort(404);
        }

        $proposal = $report->activityProposal;
        $activity = $proposal->calendarActivity;

        return [
            'logo_path' => $this->logoPath(),
            'name_of_event' => $proposal->title,
            'date_and_time_of_event' => $this->eventDateAndTime($activity),
            'activity_chairs' => implode(', ', $report->activity_chairs ?? []),
            'prepared_by' => $report->prepared_by,
            'date_submitted' => $this->dateSubmitted($document),
            'summary' => $report->summary,
            'program' => $report->event_program,
            // No attachment slot backs the "POSTER OR TITLE OF YOUR EVENT"
            // block — prints as a blank area for a physically pasted
            // printout, matching the template's own blank layout.
            'photo_filenames' => $document->attachments
                ->where('slot_key', 'photos')
                ->pluck('original_filename')
                ->all(),
            'target_participants_percentage' => $report->target_participants_percentage,
            'has_evaluation_form' => $document->attachments->contains('slot_key', 'evaluation_form'),
            'has_attendance_sheet' => $document->attachments->contains('slot_key', 'attendance_sheet'),
        ];
    }

    /**
     * Same fixed whitelist/fallback as OrganizationApplicationForm::logoPath()
     * — this template's header also carries the NU Lipa letterhead.
     */
    private function logoPath(): ?string
    {
        foreach (['print/nu-lipa-logo.svg', 'print/nu-lipa-logo.jpg'] as $relative) {
            $path = public_path($relative);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function eventDateAndTime(?CalendarActivity $activity): ?string
    {
        if ($activity === null) {
            return null;
        }

        $date = $activity->activity_date->format('m/d/Y');
        $start = Carbon::createFromFormat('H:i', $activity->start_time)->format('h:i A');
        $end = Carbon::createFromFormat('H:i', $activity->end_time)->format('h:i A');

        return "{$date}, {$start} – {$end}";
    }

    /**
     * There is no submitted_at column on documents — the submission moment
     * is a document_transitions row. Uses the LATEST submitted/resubmitted
     * transition (not the first): if this report was returned and
     * resubmitted, the printed date must reflect the version that actually
     * went through, not an earlier draft.
     */
    private function dateSubmitted(Document $document): ?string
    {
        $transition = $document->transitions
            ->filter(fn ($t) => in_array($t->action, [TransitionAction::Submitted, TransitionAction::Resubmitted], true))
            ->sortBy('created_at')
            ->last();

        return $transition?->created_at->setTimezone(self::DISPLAY_TZ)->format('m/d/Y');
    }
}
