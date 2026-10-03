<?php

namespace App\Http\Controllers;

use App\Enums\OfficerPosition;
use App\Http\Requests\Organizations\BindOfficerRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\EligibleOfficerCandidates;
use App\Organizations\OrganizationMembershipService;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationOfficerController extends Controller
{
    /**
     * Max search results returned to the bind-officer picker.
     */
    private const int SEARCH_LIMIT = 20;

    public function index(Request $request, Organization $organization, EligibleOfficerCandidates $candidates): Response
    {
        Gate::authorize('manageOfficers', $organization);

        $memberships = OrganizationMembership::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'id' => $m->id,
                'user' => ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email],
                'position' => $m->position->value,
                'position_label' => $m->position->label(),
                'academic_year' => $m->academic_year,
            ]);

        $search = $request->string('search')->trim()->toString();

        $students = $candidates->query($organization, $search)
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return Inertia::render('organizations/officers/index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'memberships' => $memberships,
            'students' => $students,
            'search' => $search,
            'positions' => collect(OfficerPosition::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function store(BindOfficerRequest $request, Organization $organization, BindOrganizationOfficer $action): RedirectResponse
    {
        Gate::authorize('manageOfficers', $organization);

        $student = User::findOrFail($request->integer('user_id'));
        $position = OfficerPosition::from($request->string('position')->toString());

        $action->execute(
            actor: Auth::user(),
            organization: $organization,
            student: $student,
            position: $position,
        );

        return redirect()->route('officers.index', $organization)
            ->with('flash', FlashToast::make('Officer added', "{$student->name} is now {$position->label()}."));
    }

    public function destroy(Organization $organization, OrganizationMembership $membership, OrganizationMembershipService $membershipService): RedirectResponse
    {
        Gate::authorize('manageOfficers', $organization);
        abort_unless($membership->organization_id === $organization->id, 404);

        // close() is a no-op on an already-inactive row — re-deactivating
        // one must never overwrite its real recorded ended_at with today's.
        $membershipService->close($membership);

        return redirect()->route('officers.index', $organization)
            ->with('flash', FlashToast::make('Officer deactivated', 'Their membership is closed and kept in the document history.'));
    }
}
