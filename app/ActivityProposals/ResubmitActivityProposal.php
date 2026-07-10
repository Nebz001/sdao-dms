<?php

namespace App\ActivityProposals;

use App\Approval\ApprovalEngine;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResubmitActivityProposal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $conflictChecker,
    ) {}

    /**
     * Edit and resubmit a Returned proposal, resuming at the returning approver.
     *
     * Accepts updated narrative fields. For off-calendar proposals the activity
     * details (venue/date/times) can also be updated; the conflict checker
     * re-runs against the new values (excluding the document's own rows).
     *
     * @param  array<string, mixed>  $data
     * @return array{document: Document, warnings: array<int, mixed>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, Document $document, array $data): array
    {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only Returned documents can be resubmitted.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may resubmit this document.');
        }

        $document->load(['activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;

        // For off-calendar, optionally update the CalendarActivity details first.
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $activity = $proposal->calendarActivity;

            if ($activity !== null && $this->hasActivityUpdate($data)) {
                $activity->update(array_filter([
                    'name' => $data['title'] ?? $activity->name,
                    'venue' => $data['venue'] ?? $activity->venue,
                    'activity_date' => $data['activity_date'] ?? $activity->activity_date->toDateString(),
                    'start_time' => $data['start_time'] ?? $activity->start_time,
                    'end_time' => $data['end_time'] ?? $activity->end_time,
                ], fn ($v) => $v !== null));

                $activity->refresh();
            }

            // Hard-block: re-check against Approved slots (exclude self — document is Returned).
            if ($activity !== null) {
                $this->guardConfirmedConflicts($activity, $document->id);
            }
        }

        $document = DB::transaction(function () use ($actor, $document, $proposal, $data) {
            $proposal->update([
                'objectives' => $data['objectives'],
                'narrative' => $data['narrative'],
                'proposed_budget' => $data['proposed_budget'] ?? $proposal->proposed_budget,
                // Exact field corrections (Phase 2 item 7 slice 4a) — editable
                // on resubmission, same as proposed_budget already is.
                'activity_nature' => $data['activity_nature'] ?? $proposal->activity_nature,
                'activity_type' => $data['activity_type'] ?? $proposal->activity_type,
                'partner_organizations' => $data['partner_organizations'] ?? $proposal->partner_organizations,
                'target_sdg' => $data['target_sdg'] ?? $proposal->target_sdg,
                'budget_source' => $data['budget_source'] ?? $proposal->budget_source,
            ]);

            if (isset($data['title'])) {
                $proposal->update(['title' => $data['title']]);
            }

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });

        // Non-blocking tentative warnings after resubmit.
        $warnings = [];
        if ($proposal->calendar_mode === ProposalCalendarMode::OffCalendar && $proposal->calendarActivity !== null) {
            $warnings = $this->collectTentativeWarnings($proposal->calendarActivity, $document->id);
        }

        return ['document' => $document, 'warnings' => $warnings];
    }

    /** @param  array<string, mixed>  $data */
    private function hasActivityUpdate(array $data): bool
    {
        return isset($data['title']) || isset($data['venue']) || isset($data['activity_date'])
            || isset($data['start_time']) || isset($data['end_time']);
    }

    /** @throws ValidationException */
    private function guardConfirmedConflicts(CalendarActivity $activity, int $excludeDocumentId): void
    {
        $conflicts = $this->conflictChecker->confirmedConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $excludeDocumentId,
        );

        if ($conflicts->isNotEmpty()) {
            $names = $conflicts->map(fn ($c) => "\"{$c->name}\" ({$c->calendar->document->organization->name})")->implode(', ');

            throw ValidationException::withMessages([
                'activity' => "This activity conflicts with an already-approved booking: {$names}.",
            ]);
        }
    }

    /** @return array<int, mixed> */
    private function collectTentativeWarnings(CalendarActivity $activity, int $excludeDocumentId): array
    {
        $conflicts = $this->conflictChecker->tentativeConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $excludeDocumentId,
        );

        if ($conflicts->isEmpty()) {
            return [];
        }

        return [[
            'conflicts' => $conflicts->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values()->all(),
        ]];
    }
}
