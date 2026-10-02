<?php

namespace App\Dashboard;

use App\Enums\TransitionAction;
use App\Models\DocumentTransition;
use App\Support\AcademicPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Documents submitted per week across the current term, all form types.
 * Counts `submitted` transitions only, so a resubmission after a return is not
 * a second submission. Weeks start on Monday, are half-open (a timestamp on the
 * boundary belongs to exactly one week) and are bucketed in PHP rather than with
 * Postgres date functions, so the sqlite test suite exercises the same code.
 *
 * The term's start comes from AcademicPeriod::termRange(), which reads the
 * PROVISIONAL month-to-term calendar: the system stores which term it is, not
 * the day it began.
 */
class WeeklySubmissions
{
    /** A term never charts more than this many weeks. */
    private const int MAX_WEEKS = 20;

    /**
     * @return array{termLabel: string, weeks: array<int, array{label: string, start: string, end: string, count: int, current: bool, href: string}>, thisWeek: int, lastWeek: int|null, delta: int|null}
     */
    public function forPeriod(AcademicPeriod $period, ?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        [$termStart, $termEnd] = $period->termRange();
        $upTo = $now->lessThan($termEnd) ? $now : $termEnd->copy()->subSecond();
        $firstWeek = $termStart->copy()->startOfWeek();

        if ($upTo->lessThan($firstWeek)) {
            return ['termLabel' => $period->term->label(), 'weeks' => [], 'thisWeek' => 0, 'lastWeek' => null, 'delta' => null];
        }

        $weekCount = min(self::MAX_WEEKS, (int) floor($firstWeek->diffInDays($upTo->copy()->startOfWeek()) / 7) + 1);
        $firstShown = $upTo->copy()->startOfWeek()->subWeeks($weekCount - 1);

        $submittedAt = DocumentTransition::query()
            ->where('action', TransitionAction::Submitted->value)
            ->where('created_at', '>=', $firstShown)
            ->where('created_at', '<', $firstShown->copy()->addWeeks($weekCount))
            ->pluck('created_at');

        $weeks = [];

        for ($i = 0; $i < $weekCount; $i++) {
            $start = $firstShown->copy()->addWeeks($i);
            $end = $start->copy()->addWeek();
            $isLast = $i === $weekCount - 1;

            $weeks[] = [
                'label' => $isLast && $now->lessThan($termEnd) ? 'Now' : 'W'.((int) floor($firstWeek->diffInDays($start) / 7) + 1),
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'count' => $submittedAt->filter(fn (CarbonInterface $t) => $t->greaterThanOrEqualTo($start) && $t->lessThan($end))->count(),
                'current' => $isLast && $now->lessThan($termEnd),
                'href' => route('admin.activity.index', [
                    'action' => TransitionAction::Submitted->value,
                    'from' => $start->toDateString(),
                    'to' => $end->copy()->subDay()->toDateString(),
                ]),
            ];
        }

        $thisWeek = $weeks[$weekCount - 1]['count'];
        $lastWeek = $weekCount > 1 ? $weeks[$weekCount - 2]['count'] : null;

        return [
            'termLabel' => $period->term->label(),
            'weeks' => $weeks,
            'thisWeek' => $thisWeek,
            'lastWeek' => $lastWeek,
            'delta' => $lastWeek === null ? null : $thisWeek - $lastWeek,
        ];
    }
}
