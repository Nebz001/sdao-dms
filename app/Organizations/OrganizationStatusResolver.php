<?php

namespace App\Organizations;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\OrganizationStatus;
use App\Enums\RenewalEligibility;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Renewals\SubmitOrganizationRenewal;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Illuminate\Support\Collection;

/**
 * The single source of truth for an organization's derived activity status
 * (CLAUDE.md: Active / NeedsRenewal / PendingReview / Inactive, plus the
 * orthogonal `renewalDue` flag). Never store this as a column; never
 * re-derive it inline elsewhere — every consumer (the admin organizations
 * list, the student "My Organization" page, AdminDashboardController's
 * compliance cards, OpenRenewalSeason's recipient computation) goes through
 * this class.
 *
 * Status predicate, evaluated in this order:
 *
 *   covered    = coversAcademicYearOrLater($org, $period->academicYear)
 *   hasOfficer = ANY active OrganizationMembership (president OR secretary —
 *                one is enough; see the docblock on that check below)
 *   inFlight   = a registration-or-renewal Document with
 *                DocumentStatus::isInFlight() true (Rejected excluded — see
 *                that enum method's docblock)
 *   everApproved = SubmitOrganizationRenewal::mostRecentApprovedRecord() !== null
 *
 *   covered  &&  hasOfficer  -> Active
 *   covered  && !hasOfficer  -> Inactive
 *   !covered &&  inFlight    -> PendingReview   (outranks NeedsRenewal: the
 *                                                 in-flight fact is the more
 *                                                 actionable one for a lapsed
 *                                                 org)
 *   !covered &&  everApproved -> NeedsRenewal
 *   otherwise                 -> Inactive
 *
 * DECISION — Active needs only ONE active officer, never both. A president
 * alone is sufficient: that president can add the secretary later, or a
 * secretary can join independently. Requiring both would make a normal,
 * working org read as broken during that gap. The missing role surfaces
 * ONLY as an unmet OrganizationRequirements checklist item, NEVER as a
 * status downgrade. (This also matches CLAUDE.md's Inactive clause: "covered
 * but with NO active officers" — plural read as "any", not "all".)
 *
 * DECISION — officer checks stay `is_active`-only, not scoped to the current
 * academic year. Every existing query in the app does this
 * (OrganizationMembership::scopeActive(), every OrganizationMembershipService
 * method, the isActiveOfficer shared prop). Nothing rolls memberships
 * forward at year rollover, so a year filter here would silently mark
 * currently-working orgs as officer-less.
 */
class OrganizationStatusResolver
{
    public function __construct(
        private readonly SubmitOrganizationRenewal $renewals,
    ) {}

    public function for(Organization $organization, ?AcademicPeriod $asOf = null): OrganizationStatusResult
    {
        return $this->forMany(collect([$organization]), $asOf)->get($organization->id);
    }

    /**
     * Bulk form of for() — avoids N+1 on the officer/adviser/registration/
     * in-flight inputs via one query each, composed in PHP. The
     * eligibility/coverage half still loops SubmitOrganizationRenewal::
     * eligibilityFor() per organization: it is the single source of truth
     * for renewalDue and coverage, and re-deriving it as a bulk query would
     * create the second competing definition CLAUDE.md forbids. Bounded N+1
     * at NU Lipa's scale — the same tradeoff AdminDashboardController
     * already makes for orgCompliance() and OpenRenewalSeason makes for its
     * recipient computation.
     *
     * @param  Collection<int, Organization>  $organizations
     * @return Collection<int, OrganizationStatusResult> keyed by organization id
     */
    public function forMany(Collection $organizations, ?AcademicPeriod $asOf = null): Collection
    {
        $period = $asOf ?? CurrentPeriod::get();
        $organizationIds = $organizations->pluck('id');

        $inFlightOrgIds = Document::query()
            ->whereIn('organization_id', $organizationIds)
            ->whereIn('form_type', [FormType::OrganizationRegistration->value, FormType::OrganizationRenewal->value])
            ->inFlight()
            ->pluck('organization_id')
            ->unique();

        $approvedRegistrationOrgIds = Document::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('status', DocumentStatus::Approved->value)
            ->pluck('organization_id')
            ->unique();

        $membershipsByOrg = OrganizationMembership::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('is_active', true)
            ->get(['organization_id', 'position'])
            ->groupBy('organization_id');

        $adviserBoundOrgIds = RoleAssignment::query()
            ->where('role', Role::Adviser->value)
            ->whereIn('organization_id', $organizationIds)
            ->pluck('organization_id')
            ->unique();

        return $organizations->keyBy('id')->map(fn (Organization $org) => $this->resolveOne(
            $org,
            $period,
            $inFlightOrgIds,
            $approvedRegistrationOrgIds,
            $membershipsByOrg,
            $adviserBoundOrgIds,
        ));
    }

    /**
     * "Is this org in good standing for year Y or later, right now?" —
     * approved registration OR renewal, `covers_academic_year >= Y`. This is
     * a DIFFERENT question from SubmitOrganizationRenewal::
     * hasNonRejectedRenewalForExactYear() ("has this exact year already been
     * claimed by a renewal?") and the two must never be merged — see that
     * method's docblock for the full reasoning.
     *
     * Uses the most-recent Approved record (via eligibilityFor(), which
     * already computes it) rather than scanning every approved record ever:
     * coverage carries forward from the most recent approval, exactly as
     * SubmitOrganizationRenewal::mostRecentApprovedRecord()'s docblock
     * establishes for renewal chaining.
     */
    public function coversAcademicYearOrLater(Organization $organization, string $academicYear, ?AcademicPeriod $asOf = null): bool
    {
        return $this->yearAtLeast(
            $this->renewals->eligibilityFor($organization, $asOf)->coversThroughAcademicYear,
            $academicYear,
        );
    }

    /**
     * Every organization currently due for renewal. Delegates to
     * eligibilityFor() per organization rather than a set-based SQL
     * predicate — see forMany()'s docblock for why. Replaces the inline loop
     * that used to live in OpenRenewalSeason::execute().
     *
     * @return Collection<int, int>
     */
    public function organizationIdsWithRenewalDue(?AcademicPeriod $asOf = null): Collection
    {
        return Organization::query()
            ->get(['id'])
            ->filter(fn (Organization $org) => $this->renewals->eligibilityFor($org, $asOf)->status === RenewalEligibility::Eligible)
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $inFlightOrgIds
     * @param  Collection<int, int>  $approvedRegistrationOrgIds
     * @param  Collection<int, Collection<int, OrganizationMembership>>  $membershipsByOrg
     * @param  Collection<int, int>  $adviserBoundOrgIds
     */
    private function resolveOne(
        Organization $organization,
        AcademicPeriod $period,
        Collection $inFlightOrgIds,
        Collection $approvedRegistrationOrgIds,
        Collection $membershipsByOrg,
        Collection $adviserBoundOrgIds,
    ): OrganizationStatusResult {
        $eligibility = $this->renewals->eligibilityFor($organization, $period);

        $coversThrough = $eligibility->coversThroughAcademicYear;
        $covered = $this->yearAtLeast($coversThrough, $period->academicYear);
        $everApproved = $eligibility->priorRecord !== null;
        $inFlight = $inFlightOrgIds->contains($organization->id);

        $positions = ($membershipsByOrg->get($organization->id) ?? collect())
            ->pluck('position');
        $hasPresident = $positions->contains(OfficerPosition::President);
        $hasSecretary = $positions->contains(OfficerPosition::Secretary);
        $hasOfficer = $positions->isNotEmpty();

        $status = match (true) {
            $covered && $hasOfficer => OrganizationStatus::Active,
            $covered && ! $hasOfficer => OrganizationStatus::Inactive,
            ! $covered && $inFlight => OrganizationStatus::PendingReview,
            ! $covered && $everApproved => OrganizationStatus::NeedsRenewal,
            default => OrganizationStatus::Inactive,
        };

        // In renewal season, AlreadyFiledThisYear from eligibilityFor()
        // already means a non-rejected renewal exists for next year — no
        // need for a second hasNonRejectedRenewalForExactYear() query here.
        $renewalFiledForNextYear = $period->isRenewalSeason()
            && $eligibility->status === RenewalEligibility::AlreadyFiledThisYear;

        $requirements = new OrganizationRequirements(
            registrationApproved: $approvedRegistrationOrgIds->contains($organization->id),
            coverageCurrent: $covered,
            adviserBound: $adviserBoundOrgIds->contains($organization->id),
            hasPresident: $hasPresident,
            hasSecretary: $hasSecretary,
            renewalFiledForNextYear: $renewalFiledForNextYear,
        );

        return new OrganizationStatusResult(
            status: $status,
            renewalDue: $eligibility->isEligible(),
            coversThroughAcademicYear: $coversThrough,
            requirements: $requirements,
            eligibility: $eligibility,
        );
    }

    private function yearAtLeast(?string $coversThrough, string $academicYear): bool
    {
        return $coversThrough !== null && $coversThrough >= $academicYear;
    }
}
