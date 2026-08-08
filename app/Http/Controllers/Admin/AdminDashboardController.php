<?php

namespace App\Http\Controllers\Admin;

use App\Approval\StepApproverResolver;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use App\Support\AcademicYear;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SDAO's operational overview — the richer replacement for the two static
 * counts that used to be the only admin-facing content on the shared
 * dashboard. Every figure here is read-only and derived from existing data;
 * no new schema. See PLAN.md-adjacent investigation notes: "current term"
 * only exists on Activity Calendar rows, so time-scoped sections use the
 * current ACADEMIC YEAR instead — computed uniformly across all five form
 * types via `documents.created_at`, not a literal term field.
 */
class AdminDashboardController extends Controller
{
    // Matches OLDEST_IN_REVIEW_LIMIT so the two cards sharing this grid row
    // are the same height at cap, not a mismatched pair.
    private const int RECENT_ACTIVITY_LIMIT = 5;

    private const int OLDEST_IN_REVIEW_LIMIT = 5;

    /**
     * Short-chain form types reviewed by SDAO alone (CLAUDE.md "Short chains").
     *
     * @var array<int, FormType>
     */
    private const array SDAO_QUEUE_FORM_TYPES = [
        FormType::OrganizationRegistration,
        FormType::OrganizationRenewal,
        FormType::ActivityCalendar,
        FormType::AfterActivityReport,
    ];

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

    public function index(StepApproverResolver $resolver, SubmitOrganizationRenewal $renewalCheck): Response
    {
        $user = Auth::user();
        // Not sent as a page prop anymore — it's now a globally shared prop
        // (HandleInertiaRequests::share()) driving the persistent navbar
        // context strip, so every page gets it, not just this one. Still
        // needed here as a local value for orgCompliance()'s scoping.
        $academicYear = AcademicYear::current();
        [$yearStart, $yearEnd] = AcademicYear::currentRange();

        return Inertia::render('admin/dashboard', [
            'quickStats' => $this->quickStats($user, $resolver),
            'weeklyVolume' => $this->weeklyVolume(),
            'statusDistribution' => $this->statusDistribution($yearStart, $yearEnd),
            'proposalFunnel' => $this->proposalFunnel(),
            'recentActivity' => $this->recentActivity(),
            'oldestInReview' => $this->oldestInReview(),
            'orgCompliance' => $this->orgCompliance($renewalCheck, $academicYear),
        ]);
    }

    /**
     * Four counts that are today invisible until you click into their own
     * page — Pending Accounts and unassigned advisers have never been
     * surfaced anywhere admin-facing before this. Two of the four also carry
     * a `weekly` baseline (this-week vs. last-week) for the frontend's trend
     * indicator — omitted, not zeroed, on the other two, since neither has
     * an honest historical baseline: "Proposals At Your Step" resolves
     * against the document's *current* step position, which isn't
     * reconstructible for a past date, and "Unassigned Advisers" is a
     * point-in-time assignment state, not an event with a timestamp to
     * bucket.
     *
     * @return array<int, array{label: string, count: int, href: string|null, weekly?: array{thisWeek: int, lastWeek: int, delta: int, noun: string}, urgent?: bool}>
     */
    private function quickStats(User $user, StepApproverResolver $resolver): array
    {
        ['thisWeekStart' => $thisWeekStart, 'lastWeekStart' => $lastWeekStart] = $this->weekBoundaries();

        $awaitingReview = collect(self::SDAO_QUEUE_FORM_TYPES)->sum(
            fn (FormType $type) => Document::query()
                ->where('form_type', $type->value)
                ->where('status', DocumentStatus::InReview->value)
                ->count()
        );

        $shortChainFormTypeValues = collect(self::SDAO_QUEUE_FORM_TYPES)->map(fn (FormType $t) => $t->value)->all();

        $awaitingReviewWeekly = $this->bucketByWeek(
            DocumentTransition::query()
                ->where('action', TransitionAction::Submitted->value)
                ->where('created_at', '>=', $lastWeekStart)
                ->whereHas('document', fn ($q) => $q->whereIn('form_type', $shortChainFormTypeValues))
                ->pluck('created_at'),
            $thisWeekStart,
            $lastWeekStart,
        );
        $awaitingReviewWeekly['noun'] = 'submitted';

        $proposalsAtMyStep = Document::query()
            ->with('workflowTemplate.steps')
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('status', DocumentStatus::InReview->value)
            ->get()
            ->filter(function (Document $d) use ($user, $resolver) {
                try {
                    $step = $d->workflowTemplate?->steps->firstWhere('position', $d->current_step_position);

                    return $step && $resolver->approversFor($step, $d)->contains('id', $user->id);
                } catch (\Throwable) {
                    return false;
                }
            })
            ->count();

        $pendingAccounts = User::query()
            ->where('account_status', AccountStatus::Unverified->value)
            ->count();

        $pendingAccountsWeekly = $this->bucketByWeek(
            User::query()
                ->where('account_status', AccountStatus::Unverified->value)
                ->where('created_at', '>=', $lastWeekStart)
                ->pluck('created_at'),
            $thisWeekStart,
            $lastWeekStart,
        );
        $pendingAccountsWeekly['noun'] = 'registered';

        // "Available, pending assignment" — the same concept already
        // computed for the adviser-search typeahead
        // (RegistrationController::adviserSearch()'s is_available), just
        // never surfaced anywhere admin-facing before this.
        $unassignedAdvisers = RoleAssignment::query()
            ->where('role', Role::Adviser->value)
            ->whereNull('organization_id')
            ->count();

        // An unassigned adviser is a misconfiguration needing correction —
        // qualitatively different from the other three tiles' routine queue
        // depth — so it's the one tile flagged `urgent` on the frontend
        // (icon badge + pulse + tooltip, see stat-tile.tsx). Omitted, not
        // set to false, when there's nothing to flag — same convention as
        // the `weekly` key above.
        $unassignedAdvisersEntry = [
            'label' => 'Unassigned Advisers',
            'count' => $unassignedAdvisers,
            'href' => route('admin.approvers.index'),
        ];
        if ($unassignedAdvisers > 0) {
            $unassignedAdvisersEntry['urgent'] = true;
        }

        return [
            // No single href: this sums four separate queues. Links to the
            // shared dashboard, which already lists each queue individually
            // with its own link — a real destination, not a fabricated one.
            ['label' => 'Awaiting Your Review', 'count' => $awaitingReview, 'href' => route('dashboard'), 'weekly' => $awaitingReviewWeekly],
            ['label' => 'Proposals At Your Step', 'count' => $proposalsAtMyStep, 'href' => route('review.activity-proposals.index')],
            ['label' => 'Pending Accounts', 'count' => $pendingAccounts, 'href' => route('admin.pending-accounts.index'), 'weekly' => $pendingAccountsWeekly],
            $unassignedAdvisersEntry,
        ];
    }

    /**
     * This week's start and last week's start — shared by every weekly
     * comparison on this page (the global "documents submitted this week"
     * line and the two quick-stat baselines), so all three measure the same
     * week consistently.
     *
     * @return array{thisWeekStart: CarbonInterface, lastWeekStart: CarbonInterface}
     */
    private function weekBoundaries(): array
    {
        $now = now();

        return [
            'thisWeekStart' => $now->copy()->startOfWeek(),
            'lastWeekStart' => $now->copy()->subWeek()->startOfWeek(),
        ];
    }

    /**
     * Buckets a collection of timestamps into "this week" vs. "last week"
     * counts. Bucketed in PHP rather than a Postgres date_trunc()/extract()
     * query so this stays exercisable under the test suite's sqlite driver.
     * `between()` is inclusive on both ends, so a timestamp landing exactly
     * on the week boundary is counted in both buckets — a pre-existing
     * characteristic carried over unchanged from the original
     * single-purpose `weeklyVolume()`, so every consumer measures the
     * boundary the same way.
     *
     * @param  Collection<int, CarbonInterface>  $timestamps
     * @return array{thisWeek: int, lastWeek: int, delta: int}
     */
    private function bucketByWeek(Collection $timestamps, CarbonInterface $thisWeekStart, CarbonInterface $lastWeekStart): array
    {
        $thisWeek = $timestamps->filter(fn (CarbonInterface $t) => $t->greaterThanOrEqualTo($thisWeekStart))->count();
        $lastWeek = $timestamps->filter(fn (CarbonInterface $t) => $t->between($lastWeekStart, $thisWeekStart))->count();

        return [
            'thisWeek' => $thisWeek,
            'lastWeek' => $lastWeek,
            'delta' => $thisWeek - $lastWeek,
        ];
    }

    /**
     * Documents submitted this ISO week vs. last — a plain comparison
     * number, not a chart (no charting library exists in this project).
     *
     * @return array{thisWeek: int, lastWeek: int, delta: int}
     */
    private function weeklyVolume(): array
    {
        ['thisWeekStart' => $thisWeekStart, 'lastWeekStart' => $lastWeekStart] = $this->weekBoundaries();

        $submittedAt = DocumentTransition::query()
            ->where('action', TransitionAction::Submitted->value)
            ->where('created_at', '>=', $lastWeekStart)
            ->pluck('created_at');

        return $this->bucketByWeek($submittedAt, $thisWeekStart, $lastWeekStart);
    }

    /**
     * All five statuses, across all five form types, scoped to the current
     * academic year — extends DocumentArchiveController's proven
     * `selectRaw('status, count(*)')` pattern to the full status set instead
     * of just the two terminal ones.
     *
     * @return array<int, array{status: string, label: string, count: int}>
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
            ])
            ->values()
            ->all();
    }

    /**
     * Where InReview activity proposals are sitting, grouped by variant then
     * role — step POSITION alone is not comparable across variants (CLAUDE.md
     * invariant #8: position 1 is the Adviser on-calendar but SDAO
     * off-calendar), so this groups by variant first and labels each step by
     * its actual role, never a raw number. The four short chains are
     * single-step and are not duplicated here — their one number already
     * lives in the quick-stats strip.
     *
     * @return array<int, array{variant: string, label: string, total: int, steps: array<int, array{role: string, count: int}>}>
     */
    private function proposalFunnel(): array
    {
        $rows = Document::query()
            ->join('workflow_steps', function ($join) {
                $join->on('workflow_steps.workflow_template_id', '=', 'documents.workflow_template_id')
                    ->on('workflow_steps.position', '=', 'documents.current_step_position');
            })
            ->where('documents.form_type', FormType::ActivityProposal->value)
            ->where('documents.status', DocumentStatus::InReview->value)
            ->selectRaw('documents.variant as variant, workflow_steps.position as position, workflow_steps.role as role, count(*) as aggregate')
            ->groupBy('documents.variant', 'workflow_steps.position', 'workflow_steps.role')
            ->orderBy('workflow_steps.position')
            ->get();

        return collect(ProposalVariant::cases())
            ->map(function (ProposalVariant $variant) use ($rows) {
                $steps = $rows->where('variant', $variant->value)
                    ->map(fn ($row) => [
                        'role' => Role::from($row->role)->label(),
                        'count' => (int) $row->aggregate,
                    ])
                    ->values();

                return [
                    'variant' => $variant->value,
                    'label' => $variant->label(),
                    'total' => (int) $steps->sum('count'),
                    'steps' => $steps->all(),
                ];
            })
            ->filter(fn (array $v) => $v['total'] > 0)
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
     * SubmitOrganizationRegistration and OrganizationOfficerController), and
     * orgs that haven't renewed for the current academic year — reusing
     * SubmitOrganizationRenewal::hasNonRejectedRenewal(), already documented
     * as the single source of truth for that check, rather than duplicating
     * it as a new subquery. At this org count (single digits to dozens),
     * checking per-org in a loop is simpler and safer than re-deriving the
     * predicate. No "fully compliant" badge: that concept isn't expressible
     * from existing data (no compliance table, no defined requirement list)
     * — deliberately not approximated.
     *
     * @return array{pending: array<int, array{organizationId: int, organizationName: string, count: int}>, notRenewed: array<int, array{organizationId: int, organizationName: string}>}
     */
    private function orgCompliance(SubmitOrganizationRenewal $renewalCheck, string $academicYear): array
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

        $notRenewed = Organization::query()
            ->get(['id', 'name'])
            ->reject(fn (Organization $org) => $renewalCheck->hasNonRejectedRenewal($org, $academicYear))
            ->map(fn (Organization $org) => ['organizationId' => $org->id, 'organizationName' => $org->name])
            ->values()
            ->all();

        return [
            'pending' => $pending,
            'notRenewed' => $notRenewed,
        ];
    }
}
