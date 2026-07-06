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
}
