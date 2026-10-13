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
        public bool $renewalFiledThisTerm,
    ) {}

    /**
     * All the requirements that gate "fully in good standing" — everything
     * except renewalFiledThisTerm, which is only meaningful while a
     * renewal is due and is surfaced separately (see toArray()'s note).
     */
    public function isComplete(): bool
    {
        return $this->registrationApproved
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
            ['key' => 'adviser_bound', 'label' => 'Adviser bound', 'met' => $this->adviserBound],
            ['key' => 'president_bound', 'label' => 'Active president', 'met' => $this->hasPresident],
            ['key' => 'secretary_bound', 'label' => 'Active secretary', 'met' => $this->hasSecretary],
            ['key' => 'renewal_filed', 'label' => 'Renewal filed this term', 'met' => $this->renewalFiledThisTerm],
        ];
    }
}
