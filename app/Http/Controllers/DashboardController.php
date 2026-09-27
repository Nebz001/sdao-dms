<?php

namespace App\Http\Controllers;

use App\Approval\ApproverDashboardData;
use App\Approval\ApproverQueue;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OrganizationJoinRequest;
use App\Support\CurrentPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        $roles = $user->roleAssignments;

        $isSdao = $roles->contains(fn ($r) => $r->role === Role::SdaoMember);

        // SDAO gets the richer operational dashboard (AdminDashboardController)
        // instead of this light multi-role page — one "Dashboard" entry point
        // for everyone, not two. See admin.dashboard.index for the real content.
        if ($isSdao) {
            return redirect()->route('admin.dashboard.index');
        }

        // Every non-Student role takes part in some activity-proposal chain
        // variant (CLAUDE.md #8 — SDAO appears once, everyone else is a
        // school/office-scoped approver). Mirrors app-sidebar.tsx's
        // PROPOSAL_APPROVER_ROLES set without re-hardcoding the same list.
        // isSdao is already false here (redirected above), but the guard
        // stays explicit for clarity rather than relying on that fact.
        $reviewsProposals = $roles->contains(fn ($r) => $r->role !== Role::Student);
        $membership = $user->organizationMemberships()->active()->with('organization')->first();

        // Every key is always present (null when the section doesn't apply)
        // rather than sometimes omitted — a predictable shape for both the
        // frontend Props type and Inertia's fluent test assertions.
        $data = [
            'myOrganization' => null,
            'approverDashboard' => null,
            'pendingJoinRequest' => null,
        ];

        // Filing a join request doesn't require AccountStatus::Verified (see
        // RequestToJoinOrganization's docblock), so this can be true even in
        // the Unverified branch below — shown there as an extra status line,
        // not a substitute for the "awaiting SDAO verification" message.
        $pendingJoinRequest = OrganizationJoinRequest::query()
            ->where('user_id', $user->id)
            ->pending()
            ->with('organization')
            ->first();

        if ($pendingJoinRequest !== null) {
            $data['pendingJoinRequest'] = [
                'organizationName' => $pendingJoinRequest->organization->name,
            ];
        }

        if ($membership !== null) {
            $needsAttentionQuery = Document::query()
                ->where('organization_id', $membership->organization_id)
                ->where(function ($q) {
                    $q->where('status', DocumentStatus::Returned->value)
                        ->orWhere(function ($q2) {
                            // Registration/renewal/calendar/report never rest in
                            // Draft (created + submitted in one transaction — see
                            // SubmitOrganizationRegistration::execute()); only the
                            // two-step proposal flow leaves a real Draft row.
                            $q2->where('status', DocumentStatus::Draft->value)
                                ->where('form_type', FormType::ActivityProposal->value);
                        });
                });

            $data['myOrganization'] = [
                'id' => $membership->organization_id,
                'name' => $membership->organization->name,
                'count' => $needsAttentionQuery->count(),
                'items' => $needsAttentionQuery
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(fn (Document $d) => [
                        'id' => $d->id,
                        'title' => $d->title,
                        'status' => $d->status->value,
                        'href' => route($d->form_type->studentShowRouteName(), $d),
                    ]),
            ];
        }

        if ($reviewsProposals) {
            $period = CurrentPeriod::get();
            $dashboard = ApproverDashboardData::for($user, $period);

            $data['approverDashboard'] = [
                'overdueAfterDays' => ApproverQueue::OVERDUE_AFTER_DAYS,
                'reviewHref' => route('review.activity-proposals.index'),
                // The KPIs below are scoped to the academic year (see
                // ApproverDashboardData::inAcademicYear()), not a narrower
                // term window, so the UI labels that too rather than saying
                // "this term".
                'academicYear' => $period->academicYear,
            ];

            // One deferred group: all seven sections resolve together in a
            // single follow-up request (and share $dashboard's own memoized
            // pending-document/outcome-count queries), so the page shell
            // paints immediately and the skeleton is replaced all at once
            // rather than section-by-section.
            $data['approverKpis'] = Inertia::defer(fn () => $dashboard->kpis(), 'approver');
            $data['approverQueue'] = Inertia::defer(fn () => $dashboard->priorityQueue(), 'approver');
            $data['approverWaitingTime'] = Inertia::defer(fn () => $dashboard->waitingTimeDistribution(), 'approver');
            $data['approverReviewActivity'] = Inertia::defer(fn () => $dashboard->reviewActivity(), 'approver');
            $data['approverOutcomeSplit'] = Inertia::defer(fn () => $dashboard->outcomeSplit(), 'approver');
            $data['approverUpcomingEvents'] = Inertia::defer(fn () => $dashboard->upcomingEvents(), 'approver');
            $data['approverRecentDecisions'] = Inertia::defer(fn () => $dashboard->recentDecisions(), 'approver');
        }

        return Inertia::render('dashboard', $data);
    }
}
