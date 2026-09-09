<?php

namespace App\Organizations;

/**
 * The orthogonal "what exactly is this org missing" checklist. Deliberately
 * separate from OrganizationStatus (CLAUDE.md's Active/NeedsRenewal/
 * PendingReview/Inactive) — an org can be Active while visibly missing a
 * secretary (decision: Active needs only ONE active officer, see
 * OrganizationStatusResolver), and this checklist is what surfaces that gap
 * without downgrading the status.
 *
 * @phpstan-type RequirementItem array{key: string, label: string, met: bool}
 */
final readonly class OrganizationRequirements
{
    public function __construct(
        public bool $registrationApproved,
        public bool $coverageCurrent,
        public bool $adviserBound,
        public bool $hasPresident,
        public bool $hasSecretary,
        public bool $renewalFiledForNextYear,
    ) {}

    /**
     * All the requirements that gate "fully in good standing" — everything
     * except renewalFiledForNextYear, which is only meaningful during
     * renewal season and is surfaced separately (see toArray()'s note).
     */
    public function isComplete(): bool
    {
        return $this->registrationApproved
            && $this->coverageCurrent
            && $this->adviserBound
            && $this->hasPresident
            && $this->hasSecretary;
    }

    /**
     * @return list<RequirementItem>
     */
    public function toArray(): array
    {
        return [
            ['key' => 'registration_approved', 'label' => 'Registration approved', 'met' => $this->registrationApproved],
            ['key' => 'coverage_current', 'label' => 'Covered for the current academic year', 'met' => $this->coverageCurrent],
            ['key' => 'adviser_bound', 'label' => 'Adviser bound', 'met' => $this->adviserBound],
            ['key' => 'president_bound', 'label' => 'Active president', 'met' => $this->hasPresident],
            ['key' => 'secretary_bound', 'label' => 'Active secretary', 'met' => $this->hasSecretary],
            ['key' => 'renewal_filed', 'label' => 'Renewal filed for next year', 'met' => $this->renewalFiledForNextYear],
        ];
    }
}
