<?php

namespace App\ActivityProposals;

use App\Enums\DocumentStatus;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;

/**
 * Enforces "at most one active proposal per on-calendar activity". Draft
 * never locks — this app has no cancel/delete-draft action, so locking on
 * Draft could strand an activity forever with nobody able to free it.
 * InReview, Returned, and Approved all lock; Rejected frees the activity
 * again immediately, since this is a live query, never a stored flag.
 * Off-calendar proposals each get their own freshly-created CalendarActivity
 * (StartProposalDraft::startOffCalendar()), so they never collide here.
 */
class OnCalendarActivityLockChecker
{
    /** @var list<string> */
    private const array LOCKING_STATUSES = [
        DocumentStatus::InReview->value,
        DocumentStatus::Returned->value,
        DocumentStatus::Approved->value,
    ];

    /**
     * Whether this activity already has another active proposal tied to
     * it. Pass $excludeDocumentId when re-checking a document that already
     * exists (the submit-time re-check) so it never blocks against itself.
     */
    public function isLocked(CalendarActivity $activity, ?int $excludeDocumentId = null): bool
    {
        return ActivityProposal::query()
            ->where('calendar_activity_id', $activity->id)
            ->whereHas('document', function ($q) use ($excludeDocumentId) {
                $q->whereIn('status', self::LOCKING_STATUSES);
                if ($excludeDocumentId !== null) {
                    $q->where('id', '!=', $excludeDocumentId);
                }
            })
            ->exists();
    }

    /**
     * IDs of every CalendarActivity currently locked by an active proposal
     * — used to exclude them from the on-calendar picker in one query.
     *
     * @return array<int, int>
     */
    public function lockedActivityIds(): array
    {
        return ActivityProposal::query()
            ->whereNotNull('calendar_activity_id')
            ->whereHas('document', fn ($q) => $q->whereIn('status', self::LOCKING_STATUSES))
            ->pluck('calendar_activity_id')
            ->all();
    }
}
