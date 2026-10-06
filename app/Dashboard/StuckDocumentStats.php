<?php

namespace App\Dashboard;

use Illuminate\Support\Collection;

/**
 * The four cards on the Stuck Documents page, built from the page's full,
 * unfiltered row set (every in-review and returned document), so they never
 * move when the filters do. Day counts come from rows that already carry
 * whole idle days measured from Document::latestTransition(); everything here
 * is plain PHP arithmetic, so the sqlite test suite runs the same code.
 *
 * Idle buckets (whole days): under 7, 7 to 14, 15 to 30, over 30.
 */
class StuckDocumentStats
{
    /** A document idle for more than this many days is "over 30 days". */
    public const int OVER_DAYS = 30;

    /**
     * The idle bucket for a number of whole days.
     */
    public static function bucketFor(int $idleDays): string
    {
        return match (true) {
            $idleDays < 7 => 'under_7',
            $idleDays <= 14 => '7_14',
            $idleDays <= self::OVER_DAYS => '15_30',
            default => 'over_30',
        };
    }

    /**
     * The status tone for an idle badge: neutral under 7 days, warning from 7
     * to 30, destructive over 30. The one place these thresholds are stated.
     */
    public static function toneFor(int $idleDays): string
    {
        return match (true) {
            $idleDays < 7 => 'neutral',
            $idleDays <= self::OVER_DAYS => 'warning',
            default => 'destructive',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{
     *     total: int, withApprovers: int, returned: int, overThirty: int,
     *     idleLongest: array{organization: string, formType: string, waitingOn: string, since: string, sinceDate: string, idleDays: int, tone: string, href: string}|null,
     *     buckets: array{under_7: int, 7_14: int, 15_30: int, over_30: int},
     *     medianDays: int,
     *     holdingMost: array{name: string, role: string, stuck: int, longestDays: int}|null,
     * }
     */
    public function summary(Collection $rows): array
    {
        $buckets = ['under_7' => 0, '7_14' => 0, '15_30' => 0, 'over_30' => 0];

        foreach ($rows as $row) {
            $buckets[self::bucketFor($row['idleDays'])]++;
        }

        return [
            'total' => $rows->count(),
            'withApprovers' => $rows->where('state', 'in_review')->count(),
            'returned' => $rows->where('state', 'returned')->count(),
            'overThirty' => $buckets['over_30'],
            'idleLongest' => $this->idleLongest($rows),
            'buckets' => $buckets,
            'medianDays' => InReviewSnapshot::median($rows->pluck('idleDays')->all()),
            'holdingMost' => $this->holdingMost($rows),
        ];
    }

    /**
     * The document idle the longest. Tie rule: the one that reached its
     * current holder earliest, then the lower document id.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{organization: string, formType: string, waitingOn: string, since: string, sinceDate: string, idleDays: int, tone: string, href: string}|null
     */
    private function idleLongest(Collection $rows): ?array
    {
        $row = $rows
            ->sort(fn (array $a, array $b) => [$b['idleDays'], $a['since'], $a['id']] <=> [$a['idleDays'], $b['since'], $b['id']])
            ->first();

        return $row === null ? null : [
            'organization' => $row['organizationName'],
            'formType' => $row['formTypeLabel'],
            'waitingOn' => $row['waitingOn'],
            'since' => $row['since'],
            'sinceDate' => $row['sinceDate'],
            'idleDays' => $row['idleDays'],
            'tone' => $row['idleTone'],
            'href' => $row['href'],
        ];
    }

    /**
     * The approver holding the most stuck documents (returned documents are
     * with an organization, and unassigned steps have no one, so neither
     * counts). Tie rule: the one whose oldest document has waited longest,
     * then the name alphabetically (case-insensitive), then the approver key.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{name: string, role: string, stuck: int, longestDays: int}|null
     */
    private function holdingMost(Collection $rows): ?array
    {
        return $rows
            ->where('state', 'in_review')
            ->reject(fn (array $row) => str_starts_with((string) $row['approverKey'], 'unassigned:'))
            ->groupBy('approverKey')
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'name' => (string) $group->first()['waitingOn'],
                'role' => (string) $group->first()['approverRole'],
                'stuck' => $group->count(),
                'longestDays' => (int) $group->max('idleDays'),
            ])
            ->sort(fn (array $a, array $b) => [$b['stuck'], $b['longestDays'], mb_strtolower($a['name']), $a['key']] <=> [$a['stuck'], $a['longestDays'], mb_strtolower($b['name']), $b['key']])
            ->map(fn (array $row) => ['name' => $row['name'], 'role' => $row['role'], 'stuck' => $row['stuck'], 'longestDays' => $row['longestDays']])
            ->first();
    }
}
