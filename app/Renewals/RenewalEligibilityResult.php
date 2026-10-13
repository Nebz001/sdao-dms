<?php

namespace App\Renewals;

use App\Enums\RenewalEligibility;
use App\Models\Document;
use App\Support\AcademicPeriod;

/**
 * The result of SubmitOrganizationRenewal::eligibilityFor() — the single
 * source of truth every renewal-adjacent surface (the submit action itself,
 * RenewalController::create()'s UX, and
 * App\Organizations\OrganizationStatusResolver's `renewalDue` flag) must
 * agree with. Never build competing "can this org renew right now" logic
 * elsewhere.
 */
final readonly class RenewalEligibilityResult
{
    public function __construct(
        public RenewalEligibility $status,
        public AcademicPeriod $currentPeriod,
        public ?string $organizationName,
        public ?AcademicPeriod $coveredThrough,
        public ?Document $priorRecord,
    ) {}

    public function isEligible(): bool
    {
        return $this->status === RenewalEligibility::Eligible;
    }

    public function message(): ?string
    {
        return match ($this->status) {
            RenewalEligibility::Eligible => null,
            RenewalEligibility::NoPriorRecord => 'This organization has no prior approved registration to renew.',
            RenewalEligibility::AlreadyFiledThisTerm => sprintf(
                'A renewal for %s has already been filed for this organization.',
                $this->currentPeriod->label(),
            ),
            RenewalEligibility::NotYetDue => sprintf(
                '%s was approved during %s, so it is already covered for this term. Its next renewal is due in the following term.',
                $this->organizationName ?? 'This organization',
                $this->currentPeriod->label(),
            ),
        };
    }
}
