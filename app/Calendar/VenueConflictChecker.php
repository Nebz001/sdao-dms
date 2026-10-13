<?php

namespace App\Calendar;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\CalendarActivity;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * What counts as booked follows the publication rule in
 * CalendarActivity::published(), the same source as the centralized calendar:
 *  - hard block: activities of fully Approved Activity Proposals;
 *  - soft warning: activities of proposals still In Review, plus planned
 *    entries of Approved Activity Calendars that have no In Review or Approved
 *    proposal yet. An Activity Calendar's own status never hard-blocks.
 */
class VenueConflictChecker
{
    /**
     * Hard-block candidates: activities of fully Approved proposals.
     * Pass `$excludeDocumentId` when re-checking a document already in the
     * database (edit / approve) to exclude that document's own rows.
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
        return $this->excluding(
            $this->overlappingSlot($venue, $date, $start, $end)->published(),
            $excludeDocumentId,
        )->get();
    }

    /**
     * Warning-only candidates: activities of proposals still In Review
     * (tentative) and planned entries on Approved Activity Calendars that have
     * no In Review or Approved proposal yet (planned).
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
        $inRoutingProposals = $this->excluding(
            $this->overlappingSlot($venue, $date, $start, $end)
                ->whereHas('proposals.document', fn (Builder $q) => $q->where('status', DocumentStatus::InReview->value)),
            $excludeDocumentId,
        )->get();

        $plannedEntries = $this->excluding(
            $this->overlappingSlot($venue, $date, $start, $end)
                ->whereHas('calendar.document', fn (Builder $q) => $q
                    ->where('form_type', FormType::ActivityCalendar->value)
                    ->where('status', DocumentStatus::Approved->value))
                ->whereDoesntHave('proposals.document', fn (Builder $q) => $q
                    ->whereIn('status', [DocumentStatus::InReview->value, DocumentStatus::Approved->value])),
            $excludeDocumentId,
        )->get();

        return $inRoutingProposals->concat($plannedEntries)->unique('id')->values();
    }

    /**
     * @return Builder<CalendarActivity>
     */
    private function overlappingSlot(string $venue, string $date, string $start, string $end): Builder
    {
        return CalendarActivity::query()
            ->where('venue', $venue)
            ->whereDate('activity_date', $date)
            ->where('start_time', '<', $end)   // strict: touching endpoint ≠ conflict
            ->where('end_time', '>', $start)   // strict
            ->with('calendar.document.organization');
    }

    /**
     * Leaves out rows owned by the given document: its own calendar rows, and
     * any row one of its proposals is linked to.
     *
     * @param  Builder<CalendarActivity>  $query
     * @return Builder<CalendarActivity>
     */
    private function excluding(Builder $query, ?int $excludeDocumentId): Builder
    {
        if ($excludeDocumentId === null) {
            return $query;
        }

        return $query
            ->whereDoesntHave('calendar', fn (Builder $q) => $q->where('document_id', $excludeDocumentId))
            ->whereDoesntHave('proposals', fn (Builder $q) => $q->where('document_id', $excludeDocumentId));
    }
}
