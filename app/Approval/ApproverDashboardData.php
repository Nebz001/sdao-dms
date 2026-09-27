<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\DisplayTimezone;
use App\Support\DocumentUrls;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read model behind the approver dashboard — one method per section. Every
 * query is scoped to $approver (as the transition actor, or as the resolved
 * approver of a document's current step) and to $period's academic year, so
 * cross-approver leakage is structural, not just filtered out after the
 * fact. Two things are computed once and reused across methods: the pending
 * ("waiting on you") document set, and the approved/returned/rejected
 * outcome counts.
 */
class ApproverDashboardData
{
    private const int QUEUE_LIMIT = 8;

    private const int RECENT_DECISIONS_LIMIT = 8;

    private const int UPCOMING_EVENTS_LIMIT = 6;

    private const int REVIEW_ACTIVITY_WEEKS = 8;

    /**
     * Actions that count as "this approver decided" — excludes Advanced/
     * Completed (quorum bookkeeping, not this actor's own decision on a
     * multi-approver step) and Withdrawn (system-attributed).
     *
     * @var array<int, TransitionAction>
     */
    private const array DECISION_ACTIONS = [
        TransitionAction::Approved,
        TransitionAction::Returned,
        TransitionAction::Rejected,
    ];

    /**
     * The three transition actions that "activate" a step — used to find
     * the moment a document reached this approver, for averageReviewTime().
     *
     * @var array<int, TransitionAction>
     */
    private const array ACTIVATION_ACTIONS = [
        TransitionAction::Submitted,
        TransitionAction::Advanced,
        TransitionAction::Resubmitted,
    ];

    /** @var EloquentCollection<int, Document>|null */
    private ?EloquentCollection $pending = null;

    /** @var array{approved: int, returned: int, rejected: int}|null */
    private ?array $outcomes = null;

    private function __construct(
        private readonly User $approver,
        private readonly AcademicPeriod $period,
        private readonly ApproverQueue $queue,
    ) {}

    public static function for(User $approver, AcademicPeriod $period): self
    {
        return new self($approver, $period, app(ApproverQueue::class));
    }

    /**
     * @return array{
     *     waitingOnYou: array{count: int, href: string},
     *     overdue: array{count: int, href: string},
     *     approved: array{count: int, href: string},
     *     returned: array{count: int, href: string},
     *     averageReviewTime: array{hours: float|null, sampleSize: int, href: string},
     * }
     */
    public function kpis(): array
    {
        $outcomes = $this->outcomeCounts();
        $overdueCount = $this->pending()->filter(fn (Document $d) => ApproverQueue::isOverdue($d))->count();

        return [
            'waitingOnYou' => [
                'count' => $this->pending()->count(),
                'href' => route('review.activity-proposals.index'),
            ],
            'overdue' => [
                'count' => $overdueCount,
                'href' => route('review.activity-proposals.index', ['filter' => 'overdue']),
            ],
            'approved' => [
                'count' => $outcomes['approved'],
                'href' => route('review.activity-proposals.index', ['filter' => 'approved']),
            ],
            'returned' => [
                'count' => $outcomes['returned'],
                'href' => route('review.activity-proposals.index', ['filter' => 'returned']),
            ],
            'averageReviewTime' => [
                ...$this->averageReviewTime(),
                'href' => route('review.activity-proposals.index', ['filter' => 'decided']),
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     id: int, title: string, formType: string, formTypeLabel: string,
     *     organizationName: string, submittedAt: string|null, waitingSince: string,
     *     daysWaiting: int, waitTier: string, wasResubmitted: bool,
     *     eventDate: string|null, eventSoon: bool, href: string,
     * }>
     */
    public function priorityQueue(): array
    {
        $today = DisplayTimezone::convert(now())->toDateString();
        $eventSoonBy = DisplayTimezone::convert(now())->addDays(ApproverQueue::EVENT_SOON_DAYS)->toDateString();

        return $this->pending()
            ->map(function (Document $d) use ($today, $eventSoonBy) {
                $sinceTransition = ApproverQueue::waitingSinceTransition($d);
                $waitingSince = $sinceTransition->created_at;
                $eventDate = $d->activityProposal?->calendarActivity?->activity_date?->toDateString();
                $eventSoon = $eventDate !== null && $eventDate >= $today && $eventDate <= $eventSoonBy;
                $urgent = ApproverQueue::isOverdue($d) || $eventSoon;

                return [
                    'row' => [
                        'id' => $d->id,
                        'title' => $d->title,
                        'formType' => $d->form_type->value,
                        'formTypeLabel' => $d->form_type->label(),
                        'organizationName' => $d->organization->name,
                        'submittedAt' => $this->submittedAt($d)?->toIso8601String(),
                        'waitingSince' => $waitingSince->toIso8601String(),
                        'daysWaiting' => (int) $waitingSince->diffInDays(now()),
                        'waitTier' => ApproverQueue::waitTier($d),
                        // Flags a document that came back to THIS SAME approver
                        // after being returned for revision — the student may
                        // have changed it since this approver last saw it, so
                        // it's worth a distinct hint from an ordinary hand-off.
                        'wasResubmitted' => $sinceTransition->action === TransitionAction::Resubmitted,
                        'eventDate' => $eventDate,
                        'eventSoon' => $eventSoon,
                        'href' => DocumentUrls::pathForReviewer($d),
                    ],
                    // Urgent (overdue or event within the window) first, then
                    // oldest-waiting first — ISO8601 timestamps sort correctly
                    // as plain strings, so a single composite key is enough.
                    'sortKey' => ($urgent ? '0-' : '1-').$waitingSince->toIso8601String(),
                ];
            })
            ->sortBy('sortKey')
            ->take(self::QUEUE_LIMIT)
            ->map(fn (array $entry) => $entry['row'])
            ->values()
            ->all();
    }

    /**
     * How long the documents in the pending queue have been waiting,
     * bucketed by the same tiers as WaitBadge/ApproverQueue::waitTier() —
     * so this chart and the queue's own badges can never disagree about
     * what "overdue" means. Reuses the already-loaded pending() set; no
     * extra query.
     *
     * @return array<int, array{tier: string, label: string, count: int}>
     */
    public function waitingTimeDistribution(): array
    {
        $counts = $this->pending()
            ->groupBy(fn (Document $d) => ApproverQueue::waitTier($d))
            ->map(fn (EloquentCollection $docs) => $docs->count());

        return [
            [
                'tier' => 'normal',
                'label' => sprintf('Under %d days', ApproverQueue::WARNING_AFTER_DAYS),
                'count' => (int) ($counts['normal'] ?? 0),
            ],
            [
                'tier' => 'warning',
                'label' => sprintf('%d–%d days', ApproverQueue::WARNING_AFTER_DAYS, ApproverQueue::OVERDUE_AFTER_DAYS),
                'count' => (int) ($counts['warning'] ?? 0),
            ],
            [
                'tier' => 'overdue',
                'label' => sprintf('Over %d days', ApproverQueue::OVERDUE_AFTER_DAYS),
                'count' => (int) ($counts['overdue'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<int, array{weekStart: string, label: string, approved: int, returned: int, rejected: int, total: int}>
     */
    public function reviewActivity(): array
    {
        $since = now()->startOfWeek()->subWeeks(self::REVIEW_ACTIVITY_WEEKS - 1);

        $transitions = $this->myDecisions()
            ->where('created_at', '>=', $since)
            ->get(['action', 'created_at']);

        // Tallied into a loosely-typed scratch array first (keyed by week
        // index, then action), then read back out below while building each
        // week's row as a single array literal — PHPStan can't track a
        // return-shaped array being incrementally mutated in place, but can
        // verify a literal built from already-known values in one go.
        $tally = [];

        foreach ($transitions as $transition) {
            $weekIndex = (int) $since->diffInWeeks($transition->created_at->copy()->startOfWeek());

            if ($weekIndex < 0 || $weekIndex >= self::REVIEW_ACTIVITY_WEEKS) {
                continue;
            }

            $key = match ($transition->action) {
                TransitionAction::Approved => 'approved',
                TransitionAction::Returned => 'returned',
                TransitionAction::Rejected => 'rejected',
                default => null,
            };

            if ($key === null) {
                continue;
            }

            $tally[$weekIndex][$key] = ($tally[$weekIndex][$key] ?? 0) + 1;
        }

        return collect(range(0, self::REVIEW_ACTIVITY_WEEKS - 1))
            ->map(function (int $i) use ($since, $tally) {
                $weekStart = $since->copy()->addWeeks($i);
                $approved = $tally[$i]['approved'] ?? 0;
                $returned = $tally[$i]['returned'] ?? 0;
                $rejected = $tally[$i]['rejected'] ?? 0;

                return [
                    'weekStart' => $weekStart->toDateString(),
                    'label' => $weekStart->format('M j'),
                    'approved' => $approved,
                    'returned' => $returned,
                    'rejected' => $rejected,
                    'total' => $approved + $returned + $rejected,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{approved: int, returned: int, rejected: int}
     */
    public function outcomeSplit(): array
    {
        return $this->outcomeCounts();
    }

    /**
     * @return array<int, array{id: int, title: string, organizationName: string, venue: string, date: string, startTime: string, endTime: string, href: string}>
     */
    public function upcomingEvents(): array
    {
        $today = DisplayTimezone::convert(now())->toDateString();
        $windowEnd = DisplayTimezone::convert(now())->addDays(ApproverQueue::EVENT_SOON_DAYS)->toDateString();

        $approvedDocumentIds = DocumentTransition::query()
            ->where('actor_id', $this->approver->id)
            ->where('action', TransitionAction::Approved->value)
            ->pluck('document_id');

        return ActivityProposal::query()
            ->with(['calendarActivity', 'document.organization:id,name'])
            ->whereIn('document_id', $approvedDocumentIds)
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::Approved->value))
            ->whereHas('calendarActivity', fn ($q) => $q->whereBetween('activity_date', [$today, $windowEnd]))
            ->get()
            ->filter(fn (ActivityProposal $p) => $p->calendarActivity !== null)
            ->sortBy(fn (ActivityProposal $p) => $p->calendarActivity->activity_date->toDateString().' '.$p->calendarActivity->start_time)
            ->take(self::UPCOMING_EVENTS_LIMIT)
            ->map(fn (ActivityProposal $p) => [
                'id' => $p->document_id,
                'title' => $p->title,
                'organizationName' => $p->document->organization->name,
                'venue' => $p->calendarActivity->venue,
                'date' => $p->calendarActivity->activity_date->toDateString(),
                'startTime' => $p->calendarActivity->start_time,
                'endTime' => $p->calendarActivity->end_time,
                'href' => DocumentUrls::pathForReviewer($p->document),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, action: string, formType: string, formTypeLabel: string, documentTitle: string, organizationName: string, createdAt: string, href: string}>
     */
    public function recentDecisions(): array
    {
        return $this->myDecisions()
            ->with(['document:id,title,form_type,organization_id', 'document.organization:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_DECISIONS_LIMIT)
            ->get()
            ->map(fn (DocumentTransition $t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'formType' => $t->document->form_type->value,
                'formTypeLabel' => $t->document->form_type->label(),
                'documentTitle' => $t->document->title,
                'organizationName' => $t->document->organization->name,
                'createdAt' => $t->created_at->toIso8601String(),
                'href' => DocumentUrls::pathForReviewer($t->document),
            ])
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function pending(): EloquentCollection
    {
        return $this->pending ??= $this->queue->pendingFor(
            $this->approver,
            null,
            ['activityProposal.calendarActivity', 'transitions'],
        );
    }

    /**
     * @return array{approved: int, returned: int, rejected: int}
     */
    private function outcomeCounts(): array
    {
        if ($this->outcomes !== null) {
            return $this->outcomes;
        }

        $counts = $this->inAcademicYear($this->myDecisions())
            ->selectRaw('action, count(*) as aggregate')
            ->groupBy('action')
            ->pluck('aggregate', 'action');

        return $this->outcomes = [
            'approved' => (int) ($counts[TransitionAction::Approved->value] ?? 0),
            'returned' => (int) ($counts[TransitionAction::Returned->value] ?? 0),
            'rejected' => (int) ($counts[TransitionAction::Rejected->value] ?? 0),
        ];
    }

    /**
     * Average minutes-to-decision, converted to hours. For each of this
     * approver's own decisions this academic year, finds the nearest EARLIER
     * transition on the same document at the same step position whose action
     * activates a step (Submitted/Advanced/Resubmitted) — the moment the
     * document actually reached this approver — and diffs against the
     * decision's own timestamp. Two queries total (this approver's decisions,
     * then every transition on just those documents), never per-row; the
     * average itself is taken in PHP rather than in SQL because the test
     * suite runs on SQLite and production on Postgres, whose datetime-diff
     * SQL differs (same reasoning AdminDashboardController::bucketByWeek()
     * already documents for its own weekly bucketing).
     *
     * @return array{hours: float|null, sampleSize: int}
     */
    private function averageReviewTime(): array
    {
        $decisions = $this->inAcademicYear($this->myDecisions())
            ->orderBy('id')
            ->get(['id', 'document_id', 'step_position', 'created_at']);

        if ($decisions->isEmpty()) {
            return ['hours' => null, 'sampleSize' => 0];
        }

        $documentIds = $decisions->pluck('document_id')->unique()->values();

        $transitionsByDocument = DocumentTransition::query()
            ->whereIn('document_id', $documentIds)
            ->orderBy('id')
            ->get(['id', 'document_id', 'action', 'step_position', 'created_at'])
            ->groupBy('document_id');

        $activationValues = collect(self::ACTIVATION_ACTIONS)->map(fn (TransitionAction $a) => $a->value)->all();

        /** @var Collection<int, float> $minutes */
        $minutes = $decisions
            ->map(function (DocumentTransition $decision) use ($transitionsByDocument, $activationValues) {
                $activation = $transitionsByDocument->get($decision->document_id, collect())
                    ->filter(fn (DocumentTransition $t) => $t->id < $decision->id
                        && $t->step_position === $decision->step_position
                        && in_array($t->action->value, $activationValues, true))
                    ->last();

                return $activation?->created_at->diffInMinutes($decision->created_at);
            })
            ->filter(fn (?float $m) => $m !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return ['hours' => null, 'sampleSize' => 0];
        }

        return [
            'hours' => round(((float) $minutes->avg()) / 60, 1),
            'sampleSize' => $minutes->count(),
        ];
    }

    /**
     * When a document reached ANY of its steps first became "Submitted" —
     * the same "first Submitted transition" rule
     * App\Http\Resources\Mobile\ProposalTimestamps::submittedAt() uses,
     * reimplemented here since that class lives under the mobile-API
     * namespace. Expects ->transitions already eager-loaded (see pending()).
     */
    private function submittedAt(Document $document): ?CarbonInterface
    {
        return $document->transitions
            ->first(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted)
            ?->created_at;
    }

    /**
     * @return Builder<DocumentTransition>
     */
    private function myDecisions(): Builder
    {
        return DocumentTransition::query()
            ->where('actor_id', $this->approver->id)
            ->whereIn('action', collect(self::DECISION_ACTIONS)->map(fn (TransitionAction $a) => $a->value)->all());
    }

    /**
     * @param  Builder<DocumentTransition>  $query
     * @return Builder<DocumentTransition>
     */
    private function inAcademicYear(Builder $query): Builder
    {
        [$start, $end] = $this->period->academicYearRange();

        return $query->where('created_at', '>=', $start)->where('created_at', '<', $end);
    }
}
