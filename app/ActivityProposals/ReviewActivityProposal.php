<?php

namespace App\ActivityProposals;

use App\ActivityProposals\Exceptions\ProposalVenueConflictException;
use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\DuplicateApprovalException;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Calendar\VenueConflictChecker;
use App\Enums\ProposalCalendarMode;
use App\Models\Document;
use App\Models\User;

/**
 * The single approve/reject/return entry point for Activity Proposal
 * review, shared by the web controller and the mobile API — both must go
 * through the same ApprovalEngine calls so quorum handling, the
 * append-only transition log, approver/submitter notifications and email
 * all behave identically regardless of which client acted.
 *
 * Approve alone carries extra, proposal-specific behavior: the off-calendar
 * venue-conflict race re-check (invariant #6). Reject and return have no
 * such re-check — they only ever relax a hold, never claim one — so they
 * delegate straight through to the engine.
 */
class ReviewActivityProposal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly VenueConflictChecker $checker,
    ) {}

    /**
     * @throws ProposalVenueConflictException
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     * @throws DuplicateApprovalException
     */
    public function approve(Document $document, User $actor): void
    {
        // Fresh load, not loadMissing: a rival proposal may have been
        // Approved (claiming this same slot) since this document was first
        // loaded earlier in the request/response cycle.
        $document->load('activityProposal.calendarActivity');
        $proposal = $document->activityProposal;

        if ($proposal?->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $activity = $proposal->calendarActivity;

            if ($activity !== null) {
                $conflicts = $this->checker->confirmedConflicts(
                    $activity->venue,
                    $activity->activity_date->toDateString(),
                    $activity->start_time,
                    $activity->end_time,
                    $document->id,
                );

                if ($conflicts->isNotEmpty()) {
                    throw new ProposalVenueConflictException(
                        $conflicts->first()->name,
                        $activity->venue,
                    );
                }
            }
        }

        $this->engine->approve($document, $actor);
    }

    /**
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     */
    public function reject(Document $document, User $actor, ?string $comment = null): void
    {
        $this->engine->reject($document, $actor, $comment);
    }

    /**
     * @param  array<int, string>|null  $flaggedSections
     * @param  array<string, string>|null  $sectionComments
     *
     * @throws InvalidTransitionException
     * @throws UnauthorizedApproverException
     */
    public function returnForRevision(
        Document $document,
        User $actor,
        ?string $comment = null,
        ?array $flaggedSections = null,
        ?array $sectionComments = null,
    ): void {
        $this->engine->returnForRevision($document, $actor, $comment, $flaggedSections, $sectionComments);
    }
}
