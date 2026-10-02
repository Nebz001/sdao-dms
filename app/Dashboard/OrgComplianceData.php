<?php

namespace App\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RenewalEligibility;
use App\Models\Document;
use App\Models\Organization;
use App\Organizations\OrganizationStatusResolver;
use App\Support\AcademicPeriod;

/**
 * The Organization compliance card: how many approved organizations are
 * covered, and which ones have documents still open.
 *
 * "Covered" follows the renewal season, using the resolver's own coverage and
 * eligibility facts (never a second definition of who is due):
 *   - outside 3rd term: covered for the CURRENT academic year;
 *   - in 3rd term (renewal season): covered for NEXT year, either because the
 *     registration's grace already reaches it or because a renewal has been
 *     filed (RenewalEligibility::AlreadyFiledThisYear).
 * Only organizations that have been approved at least once are counted.
 */
class OrgComplianceData
{
    /** The card lists this many organizations with pending items. */
    private const int PENDING_LIMIT = 4;

    public function __construct(private readonly OrganizationStatusResolver $statusResolver) {}

    /**
     * @return array{renewalSeason: bool, targetYear: string, done: int, total: int, pendingTotal: int, pending: array<int, array{organizationId: int, organizationName: string, count: int, href: string}>, viewAllHref: string}
     */
    public function forPeriod(AcademicPeriod $period): array
    {
        $organizations = Organization::query()->get(['id', 'name']);
        $results = $this->statusResolver->forMany($organizations, $period);

        $renewalSeason = $period->isRenewalSeason();
        $targetYear = $renewalSeason ? $period->nextAcademicYear() : $period->academicYear;

        $approved = $results->filter(fn ($result) => $result->eligibility->priorRecord !== null);
        $done = $approved->filter(fn ($result) => $result->eligibility->status === RenewalEligibility::AlreadyFiledThisYear
            || ($result->coversThroughAcademicYear !== null && $result->coversThroughAcademicYear >= $targetYear));

        $pending = Document::query()
            ->with('organization:id,name')
            ->whereIn('status', [DocumentStatus::Draft->value, DocumentStatus::InReview->value, DocumentStatus::Returned->value])
            ->get()
            ->groupBy('organization_id')
            ->map(fn ($documents) => [
                'organizationId' => (int) $documents->first()->organization_id,
                'organizationName' => $documents->first()->organization->name,
                'count' => $documents->count(),
            ])
            ->sortBy([['count', 'desc'], ['organizationName', 'asc']])
            ->values();

        return [
            'renewalSeason' => $renewalSeason,
            'targetYear' => $targetYear,
            'done' => $done->count(),
            'total' => $approved->count(),
            'pendingTotal' => $pending->count(),
            'pending' => $pending->take(self::PENDING_LIMIT)->map(fn (array $row) => [
                ...$row,
                'href' => route('admin.organizations.index', ['search' => $row['organizationName']]),
            ])->all(),
            'viewAllHref' => route('admin.organizations.index', $renewalSeason ? [] : ['status' => OrganizationStatus::NeedsRenewal->value]),        ];
    }
}
