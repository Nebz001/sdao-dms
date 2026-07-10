<?php

namespace App\Calendar;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use App\Support\CurrentTerm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitActivityCalendar
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly VenueConflictChecker $conflictChecker,
    ) {}

    /**
     * @param  array<int, array{name: string, venue: string, activity_date: string, start_time: string, end_time: string, description?: string|null, sdg?: string|null, participant_program_assigned?: string|null, budget?: string|float|null}>  $activities
     * @return array{document: Document, warnings: array<int, array{activity_index: int, conflicts: array<int, array{name: string, venue: string, activity_date: string, start_time: string, end_time: string, organization: string}>}>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        array $activities,
    ): array {
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer of this organization to submit an activity calendar.');
        }

        // Intra-calendar self-overlap check
        $this->guardIntraCalendarOverlap($activities);

        // Hard-block: any activity overlapping an already-Approved slot
        $this->guardConfirmedConflicts($activities);

        $academicYear = AcademicYear::current();
        // Term is a global, admin-controlled setting (Phase 2 item 6), not a
        // per-submission choice — read whatever is current right now and
        // stamp it on the row. A later admin change never rewrites this.
        $term = CurrentTerm::get();

        $document = DB::transaction(function () use ($actor, $organization, $term, $activities, $academicYear) {
            $document = Document::create([
                'form_type' => FormType::ActivityCalendar,
                'variant' => null,
                'title' => "Activity Calendar — {$organization->name} ({$term->label()} {$academicYear})",
                'status' => DocumentStatus::Draft,
                'current_step_position' => null,
                'organization_id' => $organization->id,
                'workflow_template_id' => null,
                'submitted_by' => $actor->id,
            ]);

            $calendar = ActivityCalendar::create([
                'document_id' => $document->id,
                'academic_year' => $academicYear,
                'term' => $term->value,
            ]);

            foreach ($activities as $activity) {
                CalendarActivity::create([
                    'activity_calendar_id' => $calendar->id,
                    'name' => $activity['name'],
                    'description' => $activity['description'] ?? null,
                    'venue' => $activity['venue'],
                    'activity_date' => $activity['activity_date'],
                    'start_time' => $activity['start_time'],
                    'end_time' => $activity['end_time'],
                    // Nullable at the DB level; StoreActivityCalendarRequest is what
                    // actually requires these for a real student submission (Phase 2
                    // item 7 slice 1) — direct callers (seeders, tests) may omit them.
                    'sdg' => $activity['sdg'] ?? null,
                    'participant_program_assigned' => $activity['participant_program_assigned'] ?? null,
                    'budget' => $activity['budget'] ?? null,
                ]);
            }

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });

        // Non-blocking tentative warnings (computed after persist so excludeDocumentId works)
        $warnings = $this->collectTentativeWarnings($activities, $document->id);

        return ['document' => $document, 'warnings' => $warnings];
    }

    /**
     * Guard: two activities in the same submission cannot overlap each other.
     *
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
     * Hard-block: throw if any submitted activity overlaps a confirmed (Approved) booking.
     *
     * @param  array<int, array{venue: string, activity_date: string, start_time: string, end_time: string}>  $activities
     *
     * @throws ValidationException
     */
    private function guardConfirmedConflicts(array $activities, ?int $excludeDocumentId = null): void
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
     * Collect tentative (InReview) warnings — non-blocking, returned to caller.
     *
     * @param  array<int, array{venue: string, activity_date: string, start_time: string, end_time: string}>  $activities
     * @return array<int, array{activity_index: int, conflicts: array<int, array{name: string, venue: string, activity_date: string, start_time: string, end_time: string, organization: string}>}>
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
