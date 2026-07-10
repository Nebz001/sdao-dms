<?php

namespace App\Calendar;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateActivityCalendar
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $conflictChecker,
    ) {}

    /**
     * @param  array<int, array{name: string, venue: string, activity_date: string, start_time: string, end_time: string, description?: string|null, sdg?: string|null, participant_program_assigned?: string|null, budget?: string|float|null}>  $activities
     * @return array{document: Document, warnings: array<int, mixed>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Document $document,
        array $activities,
    ): array {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        // Intra-calendar self-overlap check
        $this->guardIntraCalendarOverlap($activities);

        // Hard-block vs confirmed bookings (exclude self so own rows don't block)
        $this->guardConfirmedConflicts($activities, $document->id);

        $result = DB::transaction(function () use ($actor, $document, $activities) {
            $calendar = $document->activityCalendar;

            // Term is frozen at original submission (Phase 2 item 6) — a
            // resubmission after a Return never re-derives or overwrites it,
            // even if the global current term has since changed.

            // Replace all child activities
            CalendarActivity::where('activity_calendar_id', $calendar->id)->delete();

            foreach ($activities as $activity) {
                CalendarActivity::create([
                    'activity_calendar_id' => $calendar->id,
                    'name' => $activity['name'],
                    'description' => $activity['description'] ?? null,
                    'venue' => $activity['venue'],
                    'activity_date' => $activity['activity_date'],
                    'start_time' => $activity['start_time'],
                    'end_time' => $activity['end_time'],
                    'sdg' => $activity['sdg'] ?? null,
                    'participant_program_assigned' => $activity['participant_program_assigned'] ?? null,
                    'budget' => $activity['budget'] ?? null,
                ]);
            }

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });

        // Non-blocking tentative warnings
        $warnings = $this->collectTentativeWarnings($activities, $document->id);

        return ['document' => $result, 'warnings' => $warnings];
    }

    /**
     * @param  array<int, array{venue: string, activity_date: string, start_time: string, end_time: string}>  $activities
     *
     * @throws ValidationException
     */
    private function guardIntraCalendarOverlap(array $activities): void
    {
        $count = count($activities);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $activities[$i];
                $b = $activities[$j];

                if (
                    $a['venue'] === $b['venue']
                    && $a['activity_date'] === $b['activity_date']
                    && $a['start_time'] < $b['end_time']
                    && $b['start_time'] < $a['end_time']
                ) {
                    throw ValidationException::withMessages([
                        "activities.{$i}" => 'Activity overlaps with activity #'.($j + 1).' in this submission (same venue and time range).',
                        "activities.{$j}" => 'Activity overlaps with activity #'.($i + 1).' in this submission (same venue and time range).',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, array{venue: string, activity_date: string, start_time: string, end_time: string}>  $activities
     *
     * @throws ValidationException
     */
    private function guardConfirmedConflicts(array $activities, int $excludeDocumentId): void
    {
        $errors = [];

        foreach ($activities as $i => $activity) {
            $conflicts = $this->conflictChecker->confirmedConflicts(
                $activity['venue'],
                $activity['activity_date'],
                $activity['start_time'],
                $activity['end_time'],
                $excludeDocumentId,
            );

            if ($conflicts->isNotEmpty()) {
                $names = $conflicts->map(fn ($c) => "\"{$c->name}\" ({$c->calendar->document->organization->name})")->implode(', ');
                $errors["activities.{$i}"] = "This activity conflicts with an already-approved booking: {$names}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array{venue: string, activity_date: string, start_time: string, end_time: string}>  $activities
     * @return array<int, mixed>
     */
    private function collectTentativeWarnings(array $activities, int $excludeDocumentId): array
    {
        $warnings = [];

        foreach ($activities as $i => $activity) {
            $conflicts = $this->conflictChecker->tentativeConflicts(
                $activity['venue'],
                $activity['activity_date'],
                $activity['start_time'],
                $activity['end_time'],
                $excludeDocumentId,
            );

            if ($conflicts->isNotEmpty()) {
                $warnings[] = [
                    'activity_index' => $i,
                    'conflicts' => $conflicts->map(fn ($c) => [
                        'name' => $c->name,
                        'venue' => $c->venue,
                        'activity_date' => $c->activity_date->toDateString(),
                        'start_time' => $c->start_time,
                        'end_time' => $c->end_time,
                        'organization' => $c->calendar->document->organization->name,
                    ])->values()->all(),
                ];
            }
        }

        return $warnings;
    }
}
