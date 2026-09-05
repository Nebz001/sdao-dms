<?php

namespace App\Renewals;

use App\Enums\RenewalEligibility;
use App\Models\Document;
use App\Support\AcademicPeriod;

/**
 * The result of SubmitOrganizationRenewal::eligibilityFor() — the single
 * source of truth every renewal-adjacent surface (the submit action itself,
 * RenewalController::create()'s UX, and OrganizationStatusResolver's
 * `renewalDue` flag) must agree with. Never build competing "can this org
 * renew right now" logic elsewhere.
 */
final readonly class RenewalEligibilityResult
{
    public function __construct(
        public RenewalEligibility $status,
        public AcademicPeriod $currentPeriod,
        public ?string $organizationName,
        public ?string $coversThroughAcademicYear,
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
            RenewalEligibility::SeasonClosed => 'Renewal is not open yet. Organization renewal opens during 3rd Term; SDAO will notify you when it does.',
            RenewalEligibility::AlreadyFiledThisYear => sprintf(
                'A renewal covering %s has already been filed for this organization.',
                $this->currentPeriod->nextAcademicYear(),
            ),
            RenewalEligibility::NotYetDue => sprintf(
                '%s was approved during this renewal season and is already covered for %s. Its next renewal is due in 3rd Term of %s.',
                $this->organizationName ?? 'This organization',
                $this->coversThroughAcademicYear,
                $this->yearAfterCoverage(),
            ),
        };
    }

    /**
     * The academic year one beyond coversThroughAcademicYear — used only in
     * the NotYetDue message to name when the org's next renewal season falls.
     */
    private function yearAfterCoverage(): string
    {
        $startYear = (int) explode('-', (string) $this->coversThroughAcademicYear)[0];

        return ($startYear + 1).'-'.($startYear + 2);
    }
}
