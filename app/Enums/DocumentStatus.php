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
}
