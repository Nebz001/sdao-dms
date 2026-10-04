<?php

namespace App\Enums;

enum TransitionAction: string
{
    /** Student submits the document; enters the approval chain. */
    case Submitted = 'submitted';

    /** An approver approves at the current step (quorum not yet reached). */
    case Approved = 'approved';

    /** Quorum reached at a non-final step; document advanced to the next step. */
    case Advanced = 'advanced';

    /** An approver sends the document back to the student for revision. */
    case Returned = 'returned';

    /** Student resubmits after revision; chain resumes at the returning approver. */
    case Resubmitted = 'resubmitted';

    /** An approver permanently rejects the document. */
    case Rejected = 'rejected';

    /** Final step quorum reached; document is now fully approved. */
    case Completed = 'completed';

    /**
     * System-initiated permanent stop — currently only when the submitting
     * account is deleted while the document is still in flight (see
     * ApprovalEngine::withdraw()). Distinct from Rejected: nobody reviewed
     * and rejected this document, so the transition log should not claim an
     * approver did. The document's own `status` still becomes Rejected either
     * way — Approved/Rejected are the only two terminal statuses.
     */
    case Withdrawn = 'withdrawn';

    /**
     * The actions only an APPROVER performs (a decision on a document), as
     * opposed to the student-side Submitted/Resubmitted or the system's
     * Withdrawn. DocumentPolicy::hasActedOn() keys read access off these:
     * the student-side transitions also carry an actor_id, and treating
     * them as "has acted on it" would hand a removed officer permanent read
     * access to everything they ever filed.
     *
     * @return list<self>
     */
    public static function approverActions(): array
    {
        return [self::Approved, self::Advanced, self::Returned, self::Rejected, self::Completed];
    }
}
