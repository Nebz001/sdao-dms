<?php

namespace App\Http\Controllers;

use App\Enums\OfficerPosition;
use App\Http\Requests\Organizations\RequestOfficerChangeRequest;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\OrganizationMembershipService;
use App\Organizations\RequestOfficerChange;
use App\Support\FlashToast;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Officer-facing self-service alternative to Manage Officers — a current
 * president/secretary requests a change to their own org's roster, which
 * sits Pending until an SDAO admin finalizes it
 * (Admin\OfficerChangeReviewController). Additive: the adviser's direct
 * Manage Officers path (OrganizationOfficerController) is unchanged and
 * still available at any time.
 */
class OfficerChangeController extends Controller
{
    /**
     * Max search results returned to the nominee typeahead.
     */
    private const int SEARCH_LIMIT = 20;

    /**
     * Renders unconditionally, same "surface eligibility as data, not a hard
     * redirect" pattern as JoinOrganizationController::create() — a
     * non-officer still sees why they can't file, not a dead end.
     */
    public function create(OrganizationMembershipService $membershipService): Response
    {
        $user = Auth::user();
        $membership = $membershipService->activeMembershipWithOrganizationFor($user);
        $organization = $membership?->organization;

        $pendingRequest = $organization !== null
            ? OfficerChangeRequest::query()
                ->where('organization_id', $organization->id)
                ->where('requested_by', $user->id)
                ->pending()
                ->with('nominee')
                ->first()
            : null;

        $currentOfficers = $organization !== null
            ? OrganizationMembership::query()
                ->with('user')
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get()
                ->map(fn (OrganizationMembership $m) => [
                    'position' => $m->position->value,
                    'position_label' => $m->position->label(),
                    'user' => ['id' => $m->user->id, 'name' => $m->user->name],
                ])
            : [];

        return Inertia::render('organizations/officer-change/create', [
            'organization' => $organization ? ['id' => $organization->id, 'name' => $organization->name] : null,
            'currentOfficers' => $currentOfficers,
            'pendingRequest' => $pendingRequest ? [
                'position_label' => $pendingRequest->position->label(),
                'nominee' => ['name' => $pendingRequest->nominee->name],
            ] : null,
            'positions' => collect(OfficerPosition::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    /**
     * Live nominee typeahead, scoped to the actor's own org via the same
     * eligibility query the adviser's Manage Officers picker uses
     * (EligibleOfficerCandidates) — so a candidate that would be rejected at
     * submit time never appears in the list to begin with.
     *
     * @throws AuthorizationException
     */
    public function search(Request $request, OrganizationMembershipService $membershipService, EligibleOfficerCandidates $candidates): JsonResponse
    {
        $membership = $membershipService->activeMembershipWithOrganizationFor(Auth::user());

        if ($membership === null) {
            throw new AuthorizationException('Only an active officer of an organization may search for a nominee.');
        }

        $search = $request->string('q')->trim()->toString();

        $students = $candidates->query($membership->organization, $search)
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return response()->json(['students' => $students]);
    }

    public function store(RequestOfficerChangeRequest $request, RequestOfficerChange $action): RedirectResponse
    {
        $position = OfficerPosition::from($request->string('position')->toString());
        $nominee = User::findOrFail($request->integer('nominee_id'));

        $action->execute(
            actor: Auth::user(),
            position: $position,
            nominee: $nominee,
            reason: $request->string('reason')->toString() ?: null,
        );

        return redirect()->route('organizations.officer-change.create')
            ->with('flash', FlashToast::make('Request sent', 'An SDAO admin will review the officer change.'));
    }
}
