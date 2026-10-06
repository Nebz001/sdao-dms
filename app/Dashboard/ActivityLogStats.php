<?php

namespace App\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\WorkflowStep;
use App\Support\AcademicPeriod;
use App\Support\DisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The four cards on the Activity Log page. Every figure is scoped to the
 * current term (AcademicPeriod::termRange(), from the CurrentPeriod setting)
 * and never to the page's filters, so the cards stay put while filtering.
 * All date math is done in PHP, not SQL date functions, so the sqlite test
 * suite runs the same code as Postgres.
 *
 * Event groups (every event lands in exactly one, so the groups add up to the
 * total): Submitted = submitted + resubmitted; Approved = approved + advanced
 * + completed; Returned = returned; Rejected = rejected + withdrawn.
 */
class ActivityLogStats
{
    /** How many weeks the "Events this week" chart shows, the running week included. */
    public const int WEEKS = 8;

    /** Finished waits a role needs before it can be named the slowest step. */
    public const int MIN_FINISHED_WAITS = 3;

    /**
     * Legend group for each action, in the order the legend lists them.
     *
     * @var array<string, array<int, TransitionAction>>
     */
    public const array GROUPS = [
        'submitted' => [TransitionAction::Submitted, TransitionAction::Resubmitted],
        'approved' => [TransitionAction::Approved, TransitionAction::Advanced, TransitionAction::Completed],
        'returned' => [TransitionAction::Returned],
        'rejected' => [TransitionAction::Rejected, TransitionAction::Withdrawn],
    ];

    /** The transitions that hand a document to a step (the start of a wait). */
    private const array HAND_OFFS = [
        TransitionAction::Submitted,
        TransitionAction::Advanced,
        TransitionAction::Resubmitted,
    ];

    /**
     * @return array{
     *     termLabel: string,
     *     total: array{total: int, submitted: int, approved: int, returned: int, rejected: int},
     *     perWeek: array{thisWeek: int, weeks: array<int, int>},
     *     slowestStep: array{role: string, label: string, avgDays: float, finishedWaits: int, waitingNow: int}|null,
     *     topOrganization: array{id: int, name: string, events: int, submitted: int}|null,
     * }
     */
    public function summary(AcademicPeriod $period, ?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        [$termStart, $termEnd] = $period->termRange();

        $events = DocumentTransition::query()
            ->join('documents', 'documents.id', '=', 'document_transitions.document_id')
            ->where('document_transitions.created_at', '>=', $termStart)
            ->where('document_transitions.created_at', '<', $termEnd)
            ->get([
                'document_transitions.id',
                'document_transitions.action',
                'document_transitions.created_at',
                'documents.organization_id',
            ]);

        return [
            'termLabel' => $period->term->label(),
            'total' => $this->totals($events),
            'perWeek' => $this->perWeek($events, $now),
            'slowestStep' => $this->slowestStep($termStart, $termEnd),
            'topOrganization' => $this->topOrganization($events),
        ];
    }

    /** The legend group an action belongs to. */
    public static function groupOf(TransitionAction $action): string
    {
        foreach (self::GROUPS as $group => $actions) {
            if (in_array($action, $actions, true)) {
                return $group;
            }
        }

        return 'approved';
    }

    /**
     * @param  Collection<int, DocumentTransition>  $events
     * @return array{total: int, submitted: int, approved: int, returned: int, rejected: int}
     */
    private function totals(Collection $events): array
    {
        $counts = array_fill_keys(array_keys(self::GROUPS), 0);

        foreach ($events as $event) {
            $counts[self::groupOf($event->action)]++;
        }

        return ['total' => $events->count(), ...$counts];
    }

    /**
     * Events in each of the last WEEKS Monday-start weeks (Asia/Manila weeks),
     * oldest first; the last entry is the running week. Only events inside
     * the term count, so a week that starts before the term has fewer.
     *
     * @param  Collection<int, DocumentTransition>  $events
     * @return array{thisWeek: int, weeks: array<int, int>}
     */
    private function perWeek(Collection $events, CarbonInterface $now): array
    {
        $firstWeekStart = DisplayTimezone::convert($now->copy())->startOfWeek()->subWeeks(self::WEEKS - 1);

        $weeks = [];

        for ($i = 0; $i < self::WEEKS; $i++) {
            $start = $firstWeekStart->copy()->addWeeks($i);
            $end = $start->copy()->addWeek();

            $weeks[] = $events
                ->filter(fn (DocumentTransition $e) => $e->created_at->greaterThanOrEqualTo($start) && $e->created_at->lessThan($end))
                ->count();
        }

        return ['thisWeek' => $weeks[self::WEEKS - 1], 'weeks' => $weeks];
    }

    /**
     * The approval step (grouped by ROLE, since positions differ between
     * chains) with the longest average finished wait this term.
     *
     * A wait starts at a hand-off transition (submitted, advanced, resubmitted:
     * the step_position is the step the document was handed to) and ends at the
     * next transition on the same document. A hand-off with no later transition
     * is still waiting and is left out of the average. Only roles with at least
     * MIN_FINISHED_WAITS finished waits are considered. "Waiting now" counts
     * documents In Review whose current step has that role, whatever the term.
     *
     * @return array{role: string, label: string, avgDays: float, finishedWaits: int, waitingNow: int}|null
     */
    private function slowestStep(CarbonInterface $termStart, CarbonInterface $termEnd): ?array
    {
        $handOffs = DocumentTransition::query()
            ->whereIn('action', array_map(fn (TransitionAction $a) => $a->value, self::HAND_OFFS))
            ->whereNotNull('step_position')
            ->where('created_at', '>=', $termStart)
            ->where('created_at', '<', $termEnd)
            ->get(['id', 'document_id', 'created_at', 'step_position']);

        $templateIds = Document::query()->whereIn('id', $handOffs->pluck('document_id')->unique())->pluck('workflow_template_id', 'id');
        $roleOf = $this->roleLookup($templateIds->filter()->unique()->values()->all());

        // Every transition of those documents, in order, to find each "next".
        $timeline = DocumentTransition::query()
            ->whereIn('document_id', $handOffs->pluck('document_id')->unique())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'document_id', 'created_at'])
            ->groupBy('document_id');

        /** @var array<string, array<int, float>> $waitsByRole */
        $waitsByRole = [];

        foreach ($handOffs as $handOff) {
            $role = $roleOf[$templateIds[$handOff->document_id]][$handOff->step_position] ?? null;
            $next = $timeline[$handOff->document_id]->first(
                fn (DocumentTransition $t) => [$t->created_at->getTimestamp(), $t->id] > [$handOff->created_at->getTimestamp(), $handOff->id]
            );

            if ($role === null || $next === null) {
                continue;
            }

            $waitsByRole[$role->value][] = $handOff->created_at->diffInSeconds($next->created_at, true) / 86400;
        }

        $slowest = collect($waitsByRole)
            ->filter(fn (array $waits) => count($waits) >= self::MIN_FINISHED_WAITS)
            ->map(fn (array $waits, string $role) => ['role' => $role, 'avg' => array_sum($waits) / count($waits), 'count' => count($waits)])
            ->sort(fn (array $a, array $b) => [$b['avg'], $a['role']] <=> [$a['avg'], $b['role']])
            ->first();

        if ($slowest === null) {
            return null;
        }

        $role = Role::from($slowest['role']);

        return [
            'role' => $role->value,
            'label' => self::stepLabel($role),
            'avgDays' => round($slowest['avg'], 1),
            'finishedWaits' => $slowest['count'],
            'waitingNow' => $this->waitingNow($role),
        ];
    }

    /** "SDAO review", "Dean review", "Adviser review": the same names the document view uses. */
    public static function stepLabel(Role $role): string
    {
        return ($role === Role::SdaoMember ? 'SDAO' : $role->label()).' review';
    }

    /**
     * Role of each step, keyed [template id][position].
     *
     * @param  array<int, int>  $templateIds
     * @return array<int, array<int, Role>>
     */
    private function roleLookup(array $templateIds): array
    {
        $lookup = [];

        foreach (WorkflowStep::query()->whereIn('workflow_template_id', $templateIds)->get(['workflow_template_id', 'position', 'role']) as $step) {
            $lookup[$step->workflow_template_id][$step->position] = $step->role;
        }

        return $lookup;
    }

    private function waitingNow(Role $role): int
    {
        $inReview = Document::query()
            ->where('status', DocumentStatus::InReview->value)
            ->whereNotNull('current_step_position')
            ->whereNotNull('workflow_template_id')
            ->get(['workflow_template_id', 'current_step_position']);

        $roleOf = $this->roleLookup($inReview->pluck('workflow_template_id')->unique()->values()->all());

        return $inReview
            ->filter(fn (Document $d) => ($roleOf[$d->workflow_template_id][$d->current_step_position] ?? null) === $role)
            ->count();
    }

    /**
     * The organization with the most events this term. Tie rule: the
     * organization whose name comes first alphabetically (case-insensitive),
     * then the lower id.
     *
     * @param  Collection<int, DocumentTransition>  $events
     * @return array{id: int, name: string, events: int, submitted: int}|null
     */
    private function topOrganization(Collection $events): ?array
    {
        if ($events->isEmpty()) {
            return null;
        }

        $names = Organization::query()
            ->whereIn('id', $events->pluck('organization_id')->unique())
            ->pluck('name', 'id');

        return $events
            ->groupBy('organization_id')
            ->map(fn (Collection $group, int|string $orgId) => [
                'id' => (int) $orgId,
                'name' => (string) ($names[$orgId] ?? ''),
                'events' => $group->count(),
                'submitted' => $group->filter(fn (DocumentTransition $e) => self::groupOf($e->action) === 'submitted')->count(),
            ])
            ->sort(fn (array $a, array $b) => [$b['events'], mb_strtolower($a['name']), $a['id']] <=> [$a['events'], mb_strtolower($b['name']), $b['id']])
            ->first();
    }
}
