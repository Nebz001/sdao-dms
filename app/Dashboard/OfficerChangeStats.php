<?php

namespace App\Dashboard;

use App\Approval\ReviewQueueData;
use App\Enums\OfficerChangeRequestStatus;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Models\School;
use App\Support\AcademicPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Everything the Officer Change Requests page shows.
 *
 * Waiting time is days since the request was filed, bucketed with the same
 * thresholds every review queue uses (ReviewQueueData::tierFor(): 0 to 2,
 * 3 to 7, 8+). All date math happens in PHP, never in SQL date functions, so
 * the sqlite test suite runs the same code as production.
 *
 * "This term" scopes come from AcademicPeriod::termRange(). A request has no
 * stored period of its own, so "filed this term" reads `created_at` and
 * "decided this term" reads `decided_at`. Withdrawn requests (closed by the
 * system, never reviewed) are in neither the queue nor the decided figures.
 */
class OfficerChangeStats
{
    /** How far back "Recently decided" looks. */
    public const int RECENT_DAYS = 30;

    /**
     * Pending requests, oldest first, each with its waiting time and bucket.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function queue(?CarbonInterface $now = null): Collection
    {
        $now ??= Date::now();

        $requests = $this->baseQuery()
            ->pending()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $positions = $this->requesterPositions($requests);

        return $requests->map(function (OfficerChangeRequest $r) use ($now, $positions) {
            $days = (int) $r->created_at->diffInDays($now, true);

            $currentHolderId = OrganizationMembership::query()
                ->where('organization_id', $r->organization_id)
                ->where('position', $r->position->value)
                ->where('is_active', true)
                ->value('user_id');

            return [
                ...$this->describe($r, $positions),
                'days_waiting' => $days,
                'tier' => ReviewQueueData::tierFor($days),
                // Whether the seat has changed hands since this request was
                // filed — surfaced so the admin can decline a stale request
                // instead of approving into a race.
                'is_stale' => $currentHolderId !== $r->outgoing_user_id,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $queue
     * @return array{fresh: int, aging: int, overdue: int}
     */
    public function buckets(Collection $queue): array
    {
        return ReviewQueueData::bucketCounts($queue);
    }

    /**
     * Approved and declined requests decided in the last RECENT_DAYS days,
     * newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recentlyDecided(?CarbonInterface $now = null): Collection
    {
        $now ??= Date::now();

        $requests = $this->baseQuery()
            ->whereIn('status', [OfficerChangeRequestStatus::Approved->value, OfficerChangeRequestStatus::Declined->value])
            ->where('decided_at', '>=', $now->copy()->subDays(self::RECENT_DAYS))
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->get();

        $positions = $this->requesterPositions($requests);

        return $requests->map(fn (OfficerChangeRequest $r) => [
            ...$this->describe($r, $positions),
            'result' => $r->status->value,
            'decided_at' => $r->decided_at,
            'decided_by' => $r->decider?->name,
            'decision_comment' => $r->decision_comment,
        ])->values();
    }

    /**
     * Requests filed per week across the current term, plus approved versus
     * declined decisions made in it.
     *
     * @return array{
     *     termLabel: string,
     *     submitted: array{total: int, thisWeek: int, weeks: array<int, int>},
     *     decided: array{total: int, approved: int, declined: int},
     * }
     */
    public function termActivity(AcademicPeriod $period, ?CarbonInterface $now = null): array
    {
        $now ??= Date::now();
        [$termStart, $termEnd] = $period->termRange();

        $filedAt = OfficerChangeRequest::query()
            ->where('status', '!=', OfficerChangeRequestStatus::Withdrawn->value)
            ->where('created_at', '>=', $termStart)
            ->where('created_at', '<', $termEnd)
            ->pluck('created_at');

        $decided = OfficerChangeRequest::query()
            ->whereIn('status', [OfficerChangeRequestStatus::Approved->value, OfficerChangeRequestStatus::Declined->value])
            ->where('decided_at', '>=', $termStart)
            ->where('decided_at', '<', $termEnd)
            ->pluck('status')
            ->map(fn (OfficerChangeRequestStatus|string $s) => $s instanceof OfficerChangeRequestStatus ? $s->value : $s);

        $approved = $decided->filter(fn (string $s) => $s === OfficerChangeRequestStatus::Approved->value)->count();
        $declined = $decided->filter(fn (string $s) => $s === OfficerChangeRequestStatus::Declined->value)->count();

        [$weeks, $thisWeek] = app(PendingAccountStats::class)->weeklyCounts($filedAt, $termStart, $termEnd, $now);

        return [
            'termLabel' => $period->term->label(),
            'submitted' => ['total' => $filedAt->count(), 'thisWeek' => $thisWeek, 'weeks' => $weeks],
            'decided' => ['total' => $approved + $declined, 'approved' => $approved, 'declined' => $declined],
        ];
    }

    /** @return Builder<OfficerChangeRequest> */
    private function baseQuery(): Builder
    {
        return OfficerChangeRequest::query()
            ->with(['organization.school', 'requester', 'nominee', 'outgoingOfficer', 'decider']);
    }

    /**
     * The fields every row shares. "Replace" when the seat was held when the
     * request was filed; "Add" when it was vacant. There is no "remove"
     * request in the system: a seat only ever changes hands.
     *
     * @param  array<string, string>  $positions  Requester position, keyed "orgId:userId".
     * @return array<string, mixed>
     */
    private function describe(OfficerChangeRequest $r, array $positions): array
    {
        $position = $r->position->label();
        $replacing = $r->outgoingOfficer !== null;

        return [
            'id' => $r->id,
            'organization' => ['id' => $r->organization->id, 'name' => $r->organization->name],
            'college' => $r->organization->school?->name ?? School::NONE_LABEL,
            'change_type' => ($replacing ? 'Replace ' : 'Add ').$position,
            'change_detail' => $replacing
                ? "From {$r->outgoingOfficer->name} to {$r->nominee->name}"
                : "{$r->nominee->name} as {$position}",
            'position_label' => $position,
            'requester' => ['id' => $r->requester->id, 'name' => $r->requester->name],
            'requester_position' => $positions["{$r->organization_id}:{$r->requested_by}"] ?? null,
            'nominee' => ['id' => $r->nominee->id, 'name' => $r->nominee->name],
            'outgoing_officer' => $r->outgoingOfficer ? ['id' => $r->outgoingOfficer->id, 'name' => $r->outgoingOfficer->name] : null,
            'reason' => $r->reason,
            'created_at' => $r->created_at,
        ];
    }

    /**
     * The position each requester holds (or last held) in the organization
     * they filed for, from one query. An active seat wins over an ended one.
     *
     * @param  Collection<int, OfficerChangeRequest>  $requests
     * @return array<string, string>
     */
    private function requesterPositions(Collection $requests): array
    {
        if ($requests->isEmpty()) {
            return [];
        }

        $memberships = OrganizationMembership::query()
            ->whereIn('organization_id', $requests->pluck('organization_id')->unique())
            ->whereIn('user_id', $requests->pluck('requested_by')->unique())
            ->orderBy('is_active')
            ->orderBy('started_at')
            ->get(['organization_id', 'user_id', 'position']);

        $positions = [];

        // Ascending order, so the last write per key is the active / most recent seat.
        foreach ($memberships as $m) {
            $positions["{$m->organization_id}:{$m->user_id}"] = $m->position->label();
        }

        return $positions;
    }
}
