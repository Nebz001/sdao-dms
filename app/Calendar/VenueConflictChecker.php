<?php

namespace App\Calendar;

use App\Enums\DocumentStatus;
use App\Models\CalendarActivity;
use Illuminate\Database\Eloquent\Collection;

/**
 * Server-authoritative venue overlap checker (invariant #6).
 *
 * Two activities conflict iff they share the same venue (exact string),
 * the same date, AND their time ranges OVERLAP using strict inequalities:
 *   A.start < B.end  AND  B.start < A.end
 *
 * Touching endpoints (one ends exactly when the other starts) do NOT conflict.
 * Same date/time at a different venue never conflicts.
 * Venue is free-text; there is no canonical venue list — exact string match only.
 */
class VenueConflictChecker
{
    /**
     * Find existing activities that overlap the given slot, for documents in
     * the specified statuses. Pass `$excludeDocumentId` when re-checking a
     * document already in the database (edit / approve) to exclude its own rows.
     *
     * @param  array<int, DocumentStatus>  $statuses
     * @return Collection<int, CalendarActivity>
     */
    public function overlapping(
        string $venue,
        string $date,
        string $start,
        string $end,
        array $statuses,
        ?int $excludeDocumentId = null,
    ): Collection {
        return CalendarActivity::query()
            ->whereHas('calendar.document', function ($q) use ($statuses, $excludeDocumentId) {
                $q->whereIn('status', array_map(fn (DocumentStatus $s) => $s->value, $statuses));
                if ($excludeDocumentId !== null) {
                    $q->where('id', '!=', $excludeDocumentId);
                }
            })
            ->where('venue', $venue)
            ->whereDate('activity_date', $date)
            ->where('start_time', '<', $end)   // strict: touching endpoint ≠ conflict
            ->where('end_time', '>', $start)   // strict
            ->with('calendar.document.organization')
            ->get();
    }

    /**
     * Hard-block candidates: documents with status Approved (confirmed bookings).
     *
     * @return Collection<int, CalendarActivity>
     */
    public function confirmedConflicts(
        string $venue,
        string $date,
        string $start,
        string $end,
        ?int $excludeDocumentId = null,
    ): Collection {
        return $this->overlapping($venue, $date, $start, $end, [DocumentStatus::Approved], $excludeDocumentId);
    }

    /**
     * Warning-only candidates: documents with status InReview (tentative bookings).
     *
     * @return Collection<int, CalendarActivity>
     */
    public function tentativeConflicts(
        string $venue,
        string $date,
        string $start,
        string $end,
        ?int $excludeDocumentId = null,
    ): Collection {
        return $this->overlapping($venue, $date, $start, $end, [DocumentStatus::InReview], $excludeDocumentId);
    }
}
