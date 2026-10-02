<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\AdminAttentionData;
use App\Dashboard\ProposalFunnelData;
use App\Dashboard\ReturnAnalytics;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Organizations\OrganizationStatusResolver;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SDAO's operational overview — the richer replacement for the two static
 * counts that used to be the only admin-facing content on the shared
 * dashboard. Every figure here is read-only and derived from existing data;
 * no new schema. Time-scoped sections use the current ACADEMIC YEAR (from
 * App\Support\CurrentPeriod) — computed uniformly across all five form types
 * via `documents.created_at`, since documents themselves carry no literal
 * period field.
 */
class AdminDashboardController extends Controller
{
    private const int RECENT_ACTIVITY_LIMIT = 5;

    private const int OLDEST_IN_REVIEW_LIMIT = 5;

    /**
     * Maps a document's form type to its approver-facing "show" route —
     * every document_transitions row belongs to a document that has, by
     * definition, reached InReview at least once (Draft creation never
     * writes a transition — see ApprovalEngine::submit()), so this route is
     * always reachable regardless of the document's current status.
     *
     * @var array<string, string>
     */
    private const array REVIEW_SHOW_ROUTE_NAMES = [
        'organization_registration' => 'review.registrations.show',
        'organization_renewal' => 'review.renewals.show',
        'activity_calendar' => 'review.activity-calendars.show',
        'activity_proposal' => 'review.activity-proposals.show',
        'after_activity_report' => 'review.reports.show',
    ];

    public function index(OrganizationStatusResolver $statusResolver, AdminAttentionData $attention, ProposalFunnelData $funnel, ReturnAnalytics $returnAnalytics): Response
    {
        // Not sent as a page prop anymore — it's now a globally shared prop
        // (HandleInertiaRequests::share()) driving the persistent navbar
        // context strip, so every page gets it, not just this one. Still
        // needed here as a local value for orgCompliance()'s scoping.
        $period = CurrentPeriod::get();
        [$yearStart, $yearEnd] = $period->academicYearRange();

        return Inertia::render('admin/dashboard', [
            'statusDistribution' => $this->statusDistribution($yearStart, $yearEnd),
            'proposalFunnel' => $funnel->forAcademicYear($yearStart, $yearEnd),
            // Retrospective scans of a year of transitions: deferred so the
            // operational half of the page renders first.
            'returnAnalytics' => Inertia::defer(fn () => $returnAnalytics->forAcademicYear($yearStart, $yearEnd), 'analytics'),
            'recentActivity' => $this->recentActivity(),
            'oldestInReview' => $this->oldestInReview(),
            'orgCompliance' => $this->orgCompliance($statusResolver, $period),
            'upcomingAlert' => $this->upcomingAlert($attention),
            'tiles' => $attention->tiles(),
            'stuckByApprover' => $attention->stuckByApprover(),
            'waitingSplit' => $attention->waitingSplit(),
            'oldestWaiting' => $attention->oldestInReview(),
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
     * academic year — extends DocumentArchiveController's proven
     * `selectRaw('status, count(*)')` pattern to the full status set instead
     * of just the two terminal ones.
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

    /**
     * Last 8 transitions system-wide — a bounded, glanceable teaser, not a
     * browsing surface. Deliberately capped tight (not just paginated in
     * place) so this card can never grow past its sibling
     * ("Oldest In-Review Documents") in the same grid row; genuine browsing
     * lives at the dedicated, filterable ActivityLogController instead,
     * linked via "View all activity" on the frontend.
     *
     * @return array<int, array{id: int, actorName: string, action: string, documentTitle: string, organizationName: string, createdAt: string, href: string}>
     */
    private function recentActivity(): array
    {
        return DocumentTransition::query()
            ->with([
                'actor:id,name',
                'document:id,title,form_type,organization_id',
                'document.organization:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get()
            ->map(fn (DocumentTransition $t) => [
                'id' => $t->id,
                'actorName' => $t->actor?->name ?? 'System',
                'action' => $t->action->value,
                'documentTitle' => $t->document->title,
                'organizationName' => $t->document->organization->name,
                'createdAt' => $t->created_at,
                'href' => route(self::REVIEW_SHOW_ROUTE_NAMES[$t->document->form_type->value], $t->document),
            ])
            ->values()
            ->all();
    }

    /**
     * The documents that have gone longest without activity, sorted oldest
     * first — using the LATEST transition's timestamp, not
     * documents.updated_at (see Document::latestTransition()'s docblock for
     * why that column under-counts). No hard "overdue" threshold: SDAO
     * hasn't defined an SLA, so this surfaces the list rather than
     * inventing one.
     *
     * @return array<int, array{id: int, title: string, organizationName: string, formTypeLabel: string, stepLabel: string|null, daysSinceActivity: int, href: string}>
     */
    private function oldestInReview(): array
    {
        return Document::query()
            ->with(['organization:id,name', 'latestTransition', 'workflowTemplate.steps'])
            ->where('status', DocumentStatus::InReview->value)
            ->get()
            ->map(function (Document $d) {
                $lastActivityAt = $d->latestTransition?->created_at ?? $d->created_at;
                $step = $d->workflowTemplate?->steps->firstWhere('position', $d->current_step_position);

                return [
                    'id' => $d->id,
                    'title' => $d->title,
                    'organizationName' => $d->organization->name,
                    'formTypeLabel' => $d->form_type->label(),
                    'stepLabel' => $step?->role->label(),
                    'daysSinceActivity' => (int) $lastActivityAt->diffInDays(now()),
                    'href' => route(self::REVIEW_SHOW_ROUTE_NAMES[$d->form_type->value], $d),
                ];
            })
            ->sortByDesc('daysSinceActivity')
            ->take(self::OLDEST_IN_REVIEW_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Two read-only lists: orgs with an in-flight document right now (the
     * same [Draft, InReview, Returned] idiom already used as a guard in
     * SubmitOrganizationRegistration and OrganizationOfficerController,
     * across ALL form types — deliberately broader than the resolver's
     * registration/renewal-only in-flight check, since this card means
     * "has something pending", not specifically "renewing"), and orgs whose
     * OrganizationStatusResolver-derived status is NeedsRenewal.
     *
     * Sourcing `notRenewed` from the resolver (rather than the old
     * hasNonRejectedRenewalCovering()-based approximation) fixes the bug that
     * approximation admitted: a brand-new org founded this academic year is
     * no longer false-flagged (its registration-sourced coverage is now
     * correctly recognized via coversAcademicYearOrLater()), and this list no
     * longer conflates "genuinely overdue" with "never approved at all" —
     * an org that was never approved and has nothing in flight resolves
     * Inactive, not NeedsRenewal, and is correctly absent from this list.
     *
     * @return array{pending: array<int, array{organizationId: int, organizationName: string, count: int}>, notRenewed: array<int, array{organizationId: int, organizationName: string}>}
     */
    private function orgCompliance(OrganizationStatusResolver $statusResolver, AcademicPeriod $period): array
    {
        $pending = Document::query()
            ->with('organization:id,name')
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->get()
            ->groupBy('organization_id')
            ->map(fn ($docs) => [
                'organizationId' => (int) $docs->first()->organization_id,
                'organizationName' => $docs->first()->organization->name,
                'count' => $docs->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $organizations = Organization::query()->get(['id', 'name']);
        $statuses = $statusResolver->forMany($organizations, $period);

        $notRenewed = $organizations
            ->filter(fn (Organization $org) => $statuses->get($org->id)?->status === OrganizationStatus::NeedsRenewal)
            ->map(fn (Organization $org) => ['organizationId' => $org->id, 'organizationName' => $org->name])
            ->values()
            ->all();

        return [
            'pending' => $pending,
            'notRenewed' => $notRenewed,
        ];
    }
}
