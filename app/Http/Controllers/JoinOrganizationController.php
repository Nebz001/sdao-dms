<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organizations\JoinOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Organizations\OrganizationMembershipService;
use App\Organizations\RequestToJoinOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student-facing self-service alternative to being adviser-bound: search for
 * an organization already in the system and file a request to join it. Mirrors
 * RegistrationController's create/store shape for the founding path — this is
 * its sibling, not a replacement.
 */
class JoinOrganizationController extends Controller
{
    /**
     * Max search results returned to the organization typeahead.
     */
    private const int SEARCH_LIMIT = 10;

    /**
     * Renders unconditionally (same pattern as RegistrationController::create()'s
     * `canPropose` prop) — eligibility is surfaced to the page as data, not
     * enforced as a hard redirect, so a student who's already affiliated or
     * already has a pending request still sees why, not a dead end.
     */
    public function create(OrganizationMembershipService $membershipService): Response
    {
        $user = Auth::user();

        $pendingRequest = OrganizationJoinRequest::query()
            ->where('user_id', $user->id)
            ->pending()
            ->with('organization')
            ->first();

        return Inertia::render('organizations/join/create', [
            'alreadyAffiliated' => $membershipService->hasActiveMembershipElsewhere($user),
            'pendingRequest' => $pendingRequest ? [
                'organization' => ['name' => $pendingRequest->organization->name],
            ] : null,
        ]);
    }

    /**
     * Live organization typeahead, mirrors RegistrationController::adviserSearch()'s
     * debounced-fetch pattern. Unlike that endpoint, no extra Gate is needed —
     * organization names are already public (the guest landing page's
     * calendar already lists them), so there's no new information disclosed
     * to any authenticated user searching here.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();

        $organizations = Organization::query()
            ->with(['school', 'program'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->map(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'school' => $o->school?->name,
                'program' => $o->program?->name,
            ]);

        return response()->json(['organizations' => $organizations]);
    }

    public function store(JoinOrganizationRequest $request, RequestToJoinOrganization $action): RedirectResponse
    {
        $organization = Organization::findOrFail($request->integer('organization_id'));

        $action->execute(Auth::user(), $organization);

        return redirect()->route('dashboard')
            ->with('flash', ['message' => "Request sent — {$organization->name}'s adviser will review it."]);
    }
}
