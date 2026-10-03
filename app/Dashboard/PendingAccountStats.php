<?php

namespace App\Dashboard;

use App\Approval\ReviewQueueData;
use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\AcademicPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Everything the Pending Accounts page shows beyond the list itself.
 *
 * Waiting time is days since the account registered, bucketed with the same
 * thresholds every review queue uses (ReviewQueueData::tierFor(): 0 to 2,
 * 3 to 7, 8+). All date math happens in PHP, never in SQL date functions, so
 * the sqlite test suite runs the same code as production.
 *
 * "This term" scopes come from AcademicPeriod::termRange(), which reads the
 * PROVISIONAL month-to-term calendar: the system stores which term it is, not
 * the day it began. Only self-registered STUDENT accounts count, since
 * provisioned staff never go through this queue.
 *
 * Decided counts read `account_reviewed_at`. Rows decided before that column
 * existed were backfilled from updated_at, so their term is approximate.
 */
class PendingAccountStats
{
    /** A term never charts more than this many weeks. */
    private const int MAX_WEEKS = 20;

    /**
     * Pending accounts, oldest first, each with its waiting time and bucket.
     *
     * @return Collection<int, array{id: int, name: string, email: string, id_number: string|null, created_at: CarbonInterface, days_waiting: int, tier: string}>
     */
    public function queue(?CarbonInterface $now = null): Collection
    {
        $now ??= Date::now();

        return User::query()
            ->where('account_status', AccountStatus::Unverified->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'id_number', 'created_at'])
            ->map(function (User $user) use ($now) {
                $days = (int) $user->created_at->diffInDays($now, true);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'id_number' => $user->id_number,
                    'created_at' => $user->created_at,
                    'days_waiting' => $days,
                    'tier' => ReviewQueueData::tierFor($days),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array{tier: string}>  $queue
     * @return array{fresh: int, aging: int, overdue: int}
     */
    public function buckets(Collection $queue): array
    {
        return [
            'fresh' => $queue->where('tier', 'fresh')->count(),
            'aging' => $queue->where('tier', 'aging')->count(),
            'overdue' => $queue->where('tier', 'overdue')->count(),
        ];
    }

    /**
     * Registrations per week across the current term, plus verified versus
     * rejected decisions made in it.
     *
     * @return array{
     *     termLabel: string,
     *     signedUp: array{total: int, thisWeek: int, weeks: array<int, int>},
     *     decided: array{total: int, verified: int, rejected: int},
     * }
     */
    public function termActivity(AcademicPeriod $period, ?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        [$termStart, $termEnd] = $period->termRange();

        $students = fn () => User::query()->whereNotIn('id', User::query()->nonStudent()->select('id'));

        $registeredAt = $students()
            ->where('created_at', '>=', $termStart)
            ->where('created_at', '<', $termEnd)
            ->pluck('created_at');

        $reviewed = $students()
            ->whereIn('account_status', [AccountStatus::Verified->value, AccountStatus::Rejected->value])
            ->where('account_reviewed_at', '>=', $termStart)
            ->where('account_reviewed_at', '<', $termEnd)
            ->pluck('account_status')
            ->map(fn (AccountStatus|string $status) => $status instanceof AccountStatus ? $status->value : $status);

        $verified = $reviewed->filter(fn (string $s) => $s === AccountStatus::Verified->value)->count();
        $rejected = $reviewed->filter(fn (string $s) => $s === AccountStatus::Rejected->value)->count();

        [$weeks, $thisWeek] = $this->weeklyCounts($registeredAt, $termStart, $termEnd, $now);

        return [
            'termLabel' => $period->term->label(),
            'signedUp' => ['total' => $registeredAt->count(), 'thisWeek' => $thisWeek, 'weeks' => $weeks],
            'decided' => ['total' => $verified + $rejected, 'verified' => $verified, 'rejected' => $rejected],
        ];
    }

    /**
     * Monday-start, half-open weeks from the term's first week up to now (or
     * the term's end, if it is over). The last entry is the running week.
     *
     * @param  Collection<int, CarbonInterface>  $timestamps
     * @return array{0: array<int, int>, 1: int}
     */
    private function weeklyCounts(Collection $timestamps, CarbonInterface $termStart, CarbonInterface $termEnd, CarbonInterface $now): array
    {
        $upTo = $now->lessThan($termEnd) ? $now : $termEnd->copy()->subSecond();
        $firstWeek = $termStart->copy()->startOfWeek();

        if ($upTo->lessThan($firstWeek)) {
            return [[], 0];
        }

        $weekCount = min(self::MAX_WEEKS, (int) floor($firstWeek->diffInDays($upTo->copy()->startOfWeek()) / 7) + 1);
        $firstShown = $upTo->copy()->startOfWeek()->subWeeks($weekCount - 1);

        $counts = [];

        for ($i = 0; $i < $weekCount; $i++) {
            $start = $firstShown->copy()->addWeeks($i);
            $end = $start->copy()->addWeek();

            $counts[] = $timestamps
                ->filter(fn (CarbonInterface $t) => $t->greaterThanOrEqualTo($start) && $t->lessThan($end))
                ->count();
        }

        return [$counts, $counts[$weekCount - 1]];
    }
}
