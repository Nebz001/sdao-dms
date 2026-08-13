<?php

namespace App\Http\Controllers;

use App\Enums\OfficerPosition;
use App\Http\Requests\Organizations\ApproveJoinRequestRequest;
use App\Http\Requests\Organizations\DeclineJoinRequestRequest;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\OrganizationMembership;
use App\Organizations\ApproveJoinRequest;
use App\Organizations\DeclineJoinRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Adviser/officer-facing review queue for incoming join requests — same
 * shape as the document review queues (RenewalReviewController et al.):
 * fetch every pending row, filter through the policy per-row, never
 * hardcode "adviser" (invariant #1's spirit, extended past documents).
 * Simpler than those controllers: a join request has no multi-step chain,
 * no "stale" quorum race beyond "already decided" — handled by
 * ApproveJoinRequest/DeclineJoinRequest throwing a plain ValidationException,
 * same pattern PendingAccountController relies on.
 */
class JoinRequestReviewController extends Controller
{
    public function index(): Response
    {
        $requests = OrganizationJoinRequest::query()
            ->with(['user', 'organization'])
            ->pending()
            ->orderBy('created_at')
            ->get()
            ->filter(fn (OrganizationJoinRequest $r) => Gate::allows('manageJoinRequests', $r->organization))
            ->values()
            ->map(fn (OrganizationJoinRequest $r) => [
                'id' => $r->id,
                'student' => [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ],
                'organization' => ['id' => $r->organization->id, 'name' => $r->organization->name],
                'created_at' => $r->created_at,
                'open_positions' => $this->openPositionsFor($r->organization),
            ]);

        return Inertia::render('review/join-requests/index', [
            'queue' => $requests,
            'positions' => collect(OfficerPosition::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function approve(OrganizationJoinRequest $joinRequest, ApproveJoinRequestRequest $request, ApproveJoinRequest $action): RedirectResponse
    {
        Gate::authorize('manageJoinRequests', $joinRequest->organization);

        $position = OfficerPosition::from($request->string('position')->toString());
        $studentName = $joinRequest->user->name;

        $action->execute(Auth::user(), $joinRequest, $position);

        return redirect()->route('review.join-requests.index')
            ->with('flash', ['message' => "{$studentName} added as {$position->label()}."]);
    }

    public function decline(OrganizationJoinRequest $joinRequest, DeclineJoinRequestRequest $request, DeclineJoinRequest $action): RedirectResponse
    {
        Gate::authorize('manageJoinRequests', $joinRequest->organization);

        $studentName = $joinRequest->user->name;

        $action->execute(Auth::user(), $joinRequest, $request->string('comment')->toString() ?: null);

        return redirect()->route('review.join-requests.index')
            ->with('flash', ['message' => "{$studentName}'s request was declined."]);
    }

    /**
     * @return array<int, string>
     */
    private function openPositionsFor(Organization $organization): array
    {
        // pluck('position') returns cast OfficerPosition enum instances (the
        // model's `position` cast applies even through pluck), not raw
        // strings — comparing against $p->value here would always be false.
        $filled = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->pluck('position');

        return collect(OfficerPosition::cases())
            ->reject(fn (OfficerPosition $p) => $filled->contains($p))
            ->map(fn (OfficerPosition $p) => $p->value)
            ->values()
            ->all();
    }
}
