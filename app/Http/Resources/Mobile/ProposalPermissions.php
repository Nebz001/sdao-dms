<?php

namespace App\Http\Resources\Mobile;

use App\Calendar\VenueConflictChecker;
use App\Enums\ProposalCalendarMode;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * All from DocumentPolicy — never a separate, hand-rolled rule set.
 * `can_approve` additionally re-checks the off-calendar venue conflict a
 * confirmed approval would collide with, EXCEPT in the queue
 * ($includeVenueCheck: false), where checking every listed document's
 * venue individually would defeat the queue's own N+1 avoidance (see the
 * plan's Performance section) — the detail endpoint and the approve
 * action itself are what's authoritative there.
 */
class ProposalPermissions
{
    /**
     * @return array<string, bool>
     */
    public static function for(Document $document, User $user, bool $includeVenueCheck = true): array
    {
        $canView = Gate::forUser($user)->allows('reviewView', $document);
        $canReview = Gate::forUser($user)->allows('review', $document);

        $canApprove = $canReview && (! $includeVenueCheck || ! self::hasConfirmedVenueConflict($document));

        return [
            'can_view' => $canView,
            'can_act' => $canReview,
            'can_review' => $canReview,
            'can_approve' => $canApprove,
            'can_request_revision' => $canReview,
            'can_reject' => $canReview,
        ];
    }

    private static function hasConfirmedVenueConflict(Document $document): bool
    {
        $proposal = $document->activityProposal;

        if ($proposal === null || $proposal->calendar_mode !== ProposalCalendarMode::OffCalendar) {
            return false;
        }

        $activity = $proposal->calendarActivity;

        if ($activity === null) {
            return false;
        }

        return app(VenueConflictChecker::class)->confirmedConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $document->id,
        )->isNotEmpty();
    }
}
