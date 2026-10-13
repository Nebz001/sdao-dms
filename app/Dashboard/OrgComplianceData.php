<?php

namespace App\Dashboard;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Organizations\OrganizationStatusResolver;
use App\Support\AcademicPeriod;

/**
 * The Organization compliance card: how many approved organizations are
 * covered, and which ones have documents still open.
 *
 * "Renewed" means the organization has nothing left to do for the CURRENT
 * term, using the resolver's own eligibility fact (never a second definition
 * of who is due): it filed a renewal this term, or was approved this term.
 * Organizations that have not are the ones flagged "Not yet renewed" — they
 * are only flagged, never blocked. Only organizations that have been approved at least once are counted.
 */
class OrgComplianceData
{
    /** The card lists this many organizations with pending items. */
    private const int PENDING_LIMIT = 4;

    public function __construct(private readonly OrganizationStatusResolver $statusResolver) {}

    /**
     * @return array{termLabel: string, done: int, total: int, pendingTotal: int, pending: array<int, array{organizationId: int, organizationName: string, count: int, href: string}>, viewAllHref: string}
     */
    public function forPeriod(AcademicPeriod $period): array
    {
        $organizations = Organization::query()->get(['id', 'name']);
        $results = $this->statusResolver->forMany($organizations, $period);

        $approved = $results->filter(fn ($result) => $result->eligibility->priorRecord !== null);
        $done = $approved->reject(fn ($result) => $result->renewalDue);

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
            'termLabel' => $period->label(),
            'done' => $done->count(),
            'total' => $approved->count(),
            'pendingTotal' => $pending->count(),
            'pending' => $pending->take(self::PENDING_LIMIT)->map(fn (array $row) => [
                ...$row,
                'href' => route('admin.organizations.index', ['search' => $row['organizationName']]),
            ])->all(),
            'viewAllHref' => route('admin.organizations.index', ['renewal' => 'due']),
        ];
    }
}
