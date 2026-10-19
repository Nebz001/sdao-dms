<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\WorkflowStep;
use App\Support\DisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * When the approver holding a document's current step is due to act.
 *
 * The clock starts when the document reached the step: its latest
 * document_transitions row, never documents.updated_at (see
 * Document::latestTransition()). The due time is that moment plus the step's
 * lead_time_days. All of the arithmetic is Carbon, in PHP, so it behaves the
 * same on SQLite and Postgres.
 *
 * Needs `workflowTemplate.steps` and `latestTransition` on the document;
 * eager-load them when building a list (for() loads what is missing).
 */
final readonly class StepDeadline
{
    /** Within this many hours of the due time, the state reads "due soon". */
    public const int DUE_SOON_HOURS = 24;

    public const string ON_TRACK = 'on_track';

    public const string DUE_SOON = 'due_soon';

    public const string OVERDUE = 'overdue';

    private function __construct(
        public int $stepPosition,
        public int $leadTimeDays,
        public CarbonInterface $arrivedAt,
        public CarbonInterface $dueAt,
        public int $arrivalTransitionId,
        private CarbonInterface $now,
    ) {}

    /**
     * Null unless the document is in review at a step that exists.
     */
    public static function for(Document $document, ?CarbonInterface $now = null): ?self
    {
        if ($document->status !== DocumentStatus::InReview) {
            return null;
        }

        $document->loadMissing('workflowTemplate.steps', 'latestTransition');

        $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);
        $arrival = $document->latestTransition;

        if (! $step instanceof WorkflowStep || ! $arrival instanceof DocumentTransition) {
            return null;
        }

        return self::from($step, $arrival, $now);
    }

    public static function from(WorkflowStep $step, DocumentTransition $arrival, ?CarbonInterface $now = null): self
    {
        $arrivedAt = $arrival->created_at->copy();

        return new self(
            stepPosition: $step->position,
            leadTimeDays: $step->lead_time_days,
            arrivedAt: $arrivedAt,
            dueAt: $arrivedAt->copy()->addDays($step->lead_time_days),
            arrivalTransitionId: $arrival->id,
            now: $now ?? Date::now(),
        );
    }

    public function isOverdue(): bool
    {
        return $this->now->greaterThanOrEqualTo($this->dueAt);
    }

    /** 'on_track' | 'due_soon' | 'overdue' */
    public function state(): string
    {
        if ($this->isOverdue()) {
            return self::OVERDUE;
        }

        return $this->now->copy()->addHours(self::DUE_SOON_HOURS)->greaterThanOrEqualTo($this->dueAt)
            ? self::DUE_SOON
            : self::ON_TRACK;
    }

    /** Whole days past the due time (0 on the day it became overdue). */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? intdiv($this->dueAt->diffInSeconds($this->now, true), 86400) : 0;
    }

    /** Days left, rounded up: 1 for anything under a day, 0 once overdue. */
    public function daysRemaining(): int
    {
        return $this->isOverdue() ? 0 : (int) ceil($this->now->diffInSeconds($this->dueAt, true) / 86400);
    }

    /** "Due in 2 days", "Due in 1 day", "Overdue by 1 day", "Overdue by less than a day". */
    public function label(): string
    {
        if ($this->isOverdue()) {
            $days = $this->daysOverdue();

            return $days < 1 ? 'Overdue by less than a day' : 'Overdue by '.$days.' '.($days === 1 ? 'day' : 'days');
        }

        $days = $this->daysRemaining();

        return 'Due in '.$days.' '.($days === 1 ? 'day' : 'days');
    }

    /** "3 days" / "1 day": the allotted lead time in words. */
    public function leadTimeLabel(): string
    {
        return self::leadTimeText($this->leadTimeDays);
    }

    public static function leadTimeText(int $days): string
    {
        return $days.' '.($days === 1 ? 'day' : 'days');
    }

    /**
     * The shape every page receives.
     *
     * @return array{state: string, label: string, leadTimeDays: int, leadTimeLabel: string, dueAt: string, dueDate: string, arrivedAt: string, stepPosition: int, daysOverdue: int, daysRemaining: int}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state(),
            'label' => $this->label(),
            'leadTimeDays' => $this->leadTimeDays,
            'leadTimeLabel' => $this->leadTimeLabel(),
            'dueAt' => $this->dueAt->toIso8601String(),
            'dueDate' => DisplayTimezone::convert($this->dueAt->copy())->format('n/j/Y g:i A'),
            'arrivedAt' => $this->arrivedAt->toIso8601String(),
            'stepPosition' => $this->stepPosition,
            'daysOverdue' => $this->daysOverdue(),
            'daysRemaining' => $this->daysRemaining(),
        ];
    }
}
