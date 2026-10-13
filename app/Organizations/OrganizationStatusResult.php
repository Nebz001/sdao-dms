<?php

namespace App\Organizations;

use App\Enums\OrganizationStatus;
use App\Renewals\RenewalEligibilityResult;
use App\Support\AcademicPeriod;

/**
 * The result of OrganizationStatusResolver::for()/forMany() — the single
 * source of truth for an organization's derived activity status. Mirrors the
 * shape of App\Renewals\RenewalEligibilityResult.
 */
final readonly class OrganizationStatusResult
{
    public function __construct(
        public OrganizationStatus $status,
        public bool $renewalDue,
        public ?AcademicPeriod $coveredThrough,
        public OrganizationRequirements $requirements,
        public RenewalEligibilityResult $eligibility,
    ) {}
}
