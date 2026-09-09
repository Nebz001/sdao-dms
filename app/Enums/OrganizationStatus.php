<?php

namespace App\Enums;

/**
 * An organization's derived activity status. Never stored as a column —
 * always resolved from coverage, officers and in-flight documents by
 * App\Organizations\OrganizationStatusResolver.
 */
enum OrganizationStatus: string
{
    case Active = 'active';
    case NeedsRenewal = 'needs_renewal';
    case PendingReview = 'pending_review';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::NeedsRenewal => 'Needs Renewal',
            self::PendingReview => 'Pending Review',
            self::Inactive => 'Inactive',
        };
    }
}
