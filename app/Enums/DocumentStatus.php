<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Terminal statuses cannot transition further; the student must file anew.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected => true,
            default => false,
        };
    }

    /**
     * Still moving: the document occupies its org's "one in flight at a time"
     * slot and blocks a fresh filing of the same kind.
     *
     * Rejected is deliberately NOT in flight. Reject is terminal (invariant
     * #2), and the whole point of terminating is that the org may immediately
     * file anew — including a renewal for the same covered academic year that
     * the rejected one claimed. Treating Rejected as in-flight would strand
     * the org with no way forward.
     */
    public function isInFlight(): bool
    {
        return match ($this) {
            self::Draft, self::InReview, self::Returned => true,
            default => false,
        };
    }
}
