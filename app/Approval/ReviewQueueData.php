<?php

namespace App\Approval;

use App\Dashboard\DocumentDisplayTitle;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ReviewQueueFilter;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use App\Support\CurrentPeriod;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the four SDAO review-queue pages (registrations, renewals,
 * calendars, reports) show, computed once so they can never drift apart:
 * the pending rows, the term stats and the recently-decided list.
 *
 * Reads existing data only — documents and their transition log. "Submitted"
 * is the latest `submitted`/`resubmitted` transition (the moment the document
 * landed in the approver's queue), falling back to `created_at`.
 *
 * Authorization: pending rows are filtered by the `review` ability, and every
 * figure/row derived from decided or submitted history by `reviewView`, so a
 * non-approver sees zeros and an empty list rather than other orgs' activity.
 */
class ReviewQueueData
{
    /** Days waiting at or below this are "fresh" (0 to 2 days). */
    public const int FRESH_UNTIL_DAYS = 2;

    /** Days waiting above FRESH_UNTIL_DAYS and at or below this are "aging" (3 to 7); beyond is "overdue" (8+). */
    public const int AGING_UNTIL_DAYS = 7;

    private const int RECENT_DAYS = 30;

    private const int RECENT_LIMIT = 10;

    private const int PAGE_SIZE = 10;

    private const int SPARKLINE_WEEKS = 8;

    /**
     * @param  Closure(Document): ?string  $extraValue  the page-specific column (college, period, activity)
     * @param  array<int, string>  $with  relations the closure needs
     */
    public function __construct(
        private readonly FormType $formType,
        private readonly string $showRoute,
        private readonly Closure $extraValue,
        private readonly array $with = [],
    ) {}

    /**
     * The pending rows the actor is the current approver for, oldest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function queue(): Collection
    {
        $now = Date::now();

        return Document::query()
            ->with(['organization.school', 'transitions', ...DocumentDisplayTitle::relations(), ...$this->with])
            ->where('form_type', $this->formType->value)
            ->where('status', DocumentStatus::InReview->value)
            // Defensive: a submitter-less document must never reach the queue
            // with nowhere to route its approval (documents.submitted_by is
            // nullOnDelete).
            ->whereNotNull('submitted_by')
            ->get()
            // Authorization boundary — see DocumentPolicy::review().
            ->filter(fn (Document $d) => Gate::allows('review', $d))
            ->map(function (Document $d) use ($now) {
                $submittedAt = $this->queuedAt($d);
                $days = (int) $submittedAt->diffInDays($now, true);

                return [
                    'id' => $d->id,
                    'title' => DocumentDisplayTitle::subject($d),
                    'status' => $d->status->value,
                    'current_step_position' => $d->current_step_position,
                    'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                    'created_at' => $d->created_at,
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'waiting_days' => $days,
                    'tier' => self::tierFor($days),
                    'extra' => ($this->extraValue)($d),
                    'college' => $d->organization->school?->name,
                ];
            })
            ->sortBy('submitted_at')
            ->values();
    }

    /**
     * Term-scoped figures: submissions (total + recent weekly buckets) and
     * decisions (approved / returned / rejected).
     *
     * @return array{submitted: array{total: int, thisWeek: int, weeks: array<int, int>}, decided: array{approved: int, returned: int, rejected: int, total: int}}
     */
    public function stats(User $actor): array
    {
        $now = Date::now();
        [$termStart, $termEnd] = CurrentPeriod::get()->termRange();
        $upTo = $now->lessThan($termEnd) ? $now : $termEnd->copy()->subSecond();

        $transitions = $this->visibleTransitions(
            $actor,
            [TransitionAction::Submitted, TransitionAction::Completed, TransitionAction::Returned, TransitionAction::Rejected],
            $termStart,
            $termEnd,
        );

        $submittedAt = $transitions
            ->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted)
            ->pluck('created_at');

        $decided = $transitions->reject(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted);
        $countOf = fn (TransitionAction $a) => $decided->where('action', $a)->count();

        $weeks = [];

        if ($upTo->greaterThanOrEqualTo($termStart)) {
            $currentWeek = $upTo->copy()->startOfWeek();
            $firstWeek = $termStart->copy()->startOfWeek();
            $shown = min(self::SPARKLINE_WEEKS, (int) floor($firstWeek->diffInDays($currentWeek) / 7) + 1);

            for ($i = $shown - 1; $i >= 0; $i--) {
                $start = $currentWeek->copy()->subWeeks($i);
                $end = $start->copy()->addWeek();
                $weeks[] = $submittedAt->filter(fn (CarbonInterface $t) => $t->greaterThanOrEqualTo($start) && $t->lessThan($end))->count();
            }
        }

        $approved = $countOf(TransitionAction::Completed);
        $returned = $countOf(TransitionAction::Returned);
        $rejected = $countOf(TransitionAction::Rejected);

        return [
            'submitted' => [
                'total' => $submittedAt->count(),
                'thisWeek' => $weeks === [] ? 0 : $weeks[count($weeks) - 1],
                'weeks' => $weeks,
            ],
            'decided' => [
                'approved' => $approved,
                'returned' => $returned,
                'rejected' => $rejected,
                'total' => $approved + $returned + $rejected,
            ],
        ];
    }

    /**
     * Decisions from the last 30 days, newest first.
     *
     * @return array<int, array{id: int, organization: string, college: string|null, result: string, decided_at: string, decided_by: string|null, href: string}>
     */
    public function recent(User $actor): array
    {
        $now = Date::now();

        return $this->visibleTransitions(
            $actor,
            [TransitionAction::Completed, TransitionAction::Returned, TransitionAction::Rejected],
            $now->copy()->subDays(self::RECENT_DAYS),
            $now->copy()->addSecond(),
        )
            ->sortByDesc('created_at')
            ->take(self::RECENT_LIMIT)
            ->map(fn (DocumentTransition $t) => [
                'id' => $t->id,
                'organization' => $t->document->organization->name,
                'college' => $t->document->organization->school?->name,
                'result' => match ($t->action) {
                    TransitionAction::Completed => 'approved',
                    default => $t->action->value,
                },
                'decided_at' => $t->created_at->toIso8601String(),
                'decided_by' => $t->actor?->name,
                'href' => route($this->showRoute, $t->document_id),
            ])
            ->values()
            ->all();
    }

    /**
     * Everything the tabbed queue pages need, shared so the four pages cannot
     * drift: the full pending list (for the stat cards), the tab counts, and
     * the one page of rows the selected tab shows.
     *
     * Pending and Overdue narrow the live queue. Approved, Returned and
     * All decisions are the decisions this academic year that the actor may
     * see (same `reviewView` boundary as the old "Recently decided" table, but
     * for the whole year, not 30 days and 10 rows). Approved is the final
     * approval (a Completed transition); Rejected shows only under All
     * decisions, like Activity Proposals.
     *
     * @return array<string, mixed>
     */
    public function tabbedProps(Request $request, User $actor): array
    {
        $filter = ReviewQueueFilter::tryFrom((string) $request->query('filter', ''));
        $pending = $this->queue();
        $overdue = $pending->where('tier', 'overdue')->values();

        [$yearStart, $yearEnd] = CurrentPeriod::get()->academicYearRange();
        $decisions = $this->visibleTransitions(
            $actor,
            [TransitionAction::Completed, TransitionAction::Returned, TransitionAction::Rejected],
            $yearStart,
            $yearEnd,
        )->sort(fn (DocumentTransition $a, DocumentTransition $b) => [$b->created_at, $b->id] <=> [$a->created_at, $a->id])->values();

        $countOf = fn (TransitionAction $a) => $decisions->where('action', $a)->count();

        if ($filter?->isHistory()) {
            $actions = array_map(
                fn (TransitionAction $a) => $a === TransitionAction::Approved ? TransitionAction::Completed : $a,
                $filter->actions(),
            );
            $rows = $decisions
                ->filter(fn (DocumentTransition $t) => in_array($t->action, $actions, true))
                ->map(fn (DocumentTransition $t) => [
                    'id' => $t->id,
                    'organization' => $t->document->organization->name,
                    'college' => $t->document->organization->school?->name,
                    'result' => $t->action === TransitionAction::Completed ? 'approved' : $t->action->value,
                    'decided_at' => $t->created_at->toIso8601String(),
                    'decided_by' => $t->actor?->name,
                    'href' => route($this->showRoute, $t->document_id),
                ])
                ->values();
        } else {
            $rows = $filter === ReviewQueueFilter::Overdue ? $overdue : $pending;
        }

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PAGE_SIZE)->values(),
            $rows->count(),
            self::PAGE_SIZE,
            $page,
            ['path' => $request->url(), 'query' => array_filter(['filter' => $filter?->value])],
        );

        return [
            'queue' => $pending,
            'filter' => $filter?->value,
            'filterLabel' => $filter?->label(),
            'academicYear' => CurrentPeriod::get()->academicYear,
            'tabCounts' => [
                'pending' => $pending->count(),
                'overdue' => $overdue->count(),
                'approved' => $countOf(TransitionAction::Completed),
                'returned' => $countOf(TransitionAction::Returned),
            ],
            'rows' => $paginator->items(),
            'pagination' => [
                'meta' => ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()],
                'links' => ['prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
            ],
        ];
    }

    /**
     * How many rows fall in each waiting-time tier, for the queue pages'
     * "waiting time" stat card.
     *
     * @param  Collection<int, array<string, mixed>>  $rows  each with a "tier" from tierFor()
     * @return array{fresh: int, aging: int, overdue: int}
     */
    public static function bucketCounts(Collection $rows): array
    {
        return [
            'fresh' => $rows->where('tier', 'fresh')->count(),
            'aging' => $rows->where('tier', 'aging')->count(),
            'overdue' => $rows->where('tier', 'overdue')->count(),
        ];
    }

    public static function tierFor(int $days): string
    {
        return match (true) {
            $days <= self::FRESH_UNTIL_DAYS => 'fresh',
            $days <= self::AGING_UNTIL_DAYS => 'aging',
            default => 'overdue',
        };
    }

    /**
     * Transitions of this form type in [from, to) that the actor may see.
     *
     * @param  array<int, TransitionAction>  $actions
     * @return Collection<int, DocumentTransition>
     */
    private function visibleTransitions(User $actor, array $actions, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $allowed = [];

        return DocumentTransition::query()
            ->with(['document.organization.school', 'actor'])
            ->whereIn('action', array_map(fn (TransitionAction $a) => $a->value, $actions))
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->whereHas('document', fn ($q) => $q->where('form_type', $this->formType->value))
            ->get()
            ->filter(fn (DocumentTransition $t) => $allowed[$t->document_id] ??= Gate::forUser($actor)->allows('reviewView', $t->document));
    }

    private function queuedAt(Document $document): CarbonInterface
    {
        $entry = $document->transitions
            ->whereIn('action', [TransitionAction::Submitted, TransitionAction::Resubmitted])
            ->last();

        return $entry?->created_at ?? $document->created_at;
    }
}
