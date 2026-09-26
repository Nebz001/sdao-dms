<?php

namespace App\ActivityProposals\Exceptions;

use RuntimeException;

/**
 * Thrown by ReviewActivityProposal::approve() when an off-calendar proposal's
 * venue/date/time slot has been claimed by a rival booking that was Approved
 * after this proposal entered review (the approve-time race re-check —
 * invariant #6's hard block, enforced again right before commit since the
 * document may have sat in review for a while).
 */
class ProposalVenueConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $conflictingActivityName,
        public readonly string $venue,
    ) {
        parent::__construct(
            "Cannot approve: \"{$conflictingActivityName}\" at {$venue} now conflicts with an already-approved booking. Return the document to the submitter to resolve."
        );
    }
}
