<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\AdminAttentionData;
use App\Dashboard\OrgComplianceData;
use App\Dashboard\ProposalFunnelData;
use App\Dashboard\RecentActivityFeed;
use App\Dashboard\ReturnAnalytics;
use App\Dashboard\WeeklySubmissions;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\CurrentPeriod;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SDAO's operational overview. Every figure is read-only and derived from
 * existing data, with no new schema. Each card's numbers come from one class
 * in App\Dashboard (the "waiting on an approver" figures all from
 * InReviewSnapshot), so counts agree across cards. Time-scoped sections use
 * the current ACADEMIC YEAR (App\Support\CurrentPeriod), computed uniformly
 * across all five form types via `documents.created_at`, since documents
 * carry no literal period field.
 *
 * Idle time is measured from Document::latestTransition(), never
 * documents.updated_at. The two retrospective scans (return analytics, weekly
 * submissions) are deferred so the operational half of the page renders first.
 */
class AdminDashboardController extends Controller
{
    public function index(
        AdminAttentionData $attention,
        ProposalFunnelData $funnel,
        ReturnAnalytics $returnAnalytics,
        WeeklySubmissions $weekly,
        RecentActivityFeed $recentActivity,
        OrgComplianceData $orgCompliance,
    ): Response {
        // Not sent as a page prop: the current period is a globally shared
        // prop (HandleInertiaRequests::share()) driving the navbar chips.
        $period = CurrentPeriod::get();
        [$yearStart, $yearEnd] = $period->academicYearRange();

        return Inertia::render('admin/dashboard', [
            'upcomingAlert' => $this->upcomingAlert($attention),
            'tiles' => $attention->tiles(),
            'stuckByApprover' => $attention->stuckByApprover(),
            'waitingSplit' => $attention->waitingSplit(),
            'statusDistribution' => $this->statusDistribution($yearStart, $yearEnd),
            'proposalFunnel' => $funnel->forAcademicYear($yearStart, $yearEnd),
            'returnAnalytics' => Inertia::defer(fn () => $returnAnalytics->forAcademicYear($yearStart, $yearEnd), 'analytics'),
            'recentActivity' => $recentActivity->latest(),
            'oldestInReview' => $attention->oldestInReview(),
            'weeklySubmissions' => Inertia::defer(fn () => $weekly->forPeriod($period), 'analytics'),
            'orgCompliance' => $orgCompliance->forPeriod($period),
        ]);
    }

    /**
     * The inline alert: activities within the next 7 days that are still not
     * approved. Null (so the page hides the alert) when there are none.
     *
     * @return array{count: int, names: array<int, string>, href: string}|null
     */
    private function upcomingAlert(AdminAttentionData $attention): ?array
    {
        $activities = $attention->upcomingUnapprovedActivities();

        if ($activities->isEmpty()) {
            return null;
        }

        return [
            'count' => $activities->count(),
            'names' => $activities->pluck('name')->all(),
            'href' => route('admin.stuck-documents.index', ['view' => 'upcoming']),
        ];
    }

    /**
     * Where a status segment or legend row links: the live lists for work in
     * progress, the archive (this academic year only, matching the donut) for
     * decided documents, and nothing for drafts, which have no list.
     */
    private function statusHref(DocumentStatus $status): ?string
    {
        return match ($status) {
            DocumentStatus::InReview => route('admin.stuck-documents.index', ['waiting_on' => 'approver']),
            DocumentStatus::Returned => route('admin.stuck-documents.index', ['waiting_on' => 'org']),
            DocumentStatus::Approved, DocumentStatus::Rejected => route('admin.archive.index', ['status' => $status->value, 'academic_year' => 'current']),
            DocumentStatus::Draft => null,
        };
    }

    /**
     * All five statuses, across all five form types, scoped to the current
     * academic year, extending DocumentArchiveController's
     * `selectRaw('status, count(*)')` pattern to the full status set.
     *
     * @return array<int, array{status: string, count: int, href: string|null}>
     */
    private function statusDistribution(CarbonInterface $yearStart, CarbonInterface $yearEnd): array
    {
        $counts = Document::query()
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(DocumentStatus::cases())
            ->map(fn (DocumentStatus $s) => [
                'status' => $s->value,
                'count' => (int) ($counts[$s->value] ?? 0),
                'href' => $this->statusHref($s),
            ])
            ->values()
            ->all();
    }
}
