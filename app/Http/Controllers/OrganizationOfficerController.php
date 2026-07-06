<?php

namespace App\Http\Controllers;

use App\Enums\OfficerPosition;
use App\Http\Requests\Organizations\BindOfficerRequest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationOfficerController extends Controller
{
    public function index(Organization $organization): Response
    {
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

        // Students assigned to this org who could be bound as officers.
        $students = User::query()
            ->whereHas('roleAssignments', fn ($q) => $q
                ->where('role', 'student')
                ->where('organization_id', $organization->id)
            )
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return Inertia::render('organizations/officers/index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'memberships' => $memberships,
            'students' => $students,
            'positions' => collect(OfficerPosition::cases())->map(fn ($p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function store(BindOfficerRequest $request, Organization $organization, BindOrganizationOfficer $action): RedirectResponse
    {
        $student = User::findOrFail($request->integer('user_id'));
        $position = OfficerPosition::from($request->string('position')->toString());

        $action->execute(
            actor: Auth::user(),
            organization: $organization,
            student: $student,
            position: $position,
        );

        return redirect()->route('officers.index', $organization)
            ->with('flash', ['message' => "{$student->name} bound as {$position->label()}."]);
    }

    public function destroy(Organization $organization, OrganizationMembership $membership): RedirectResponse
    {
        $membership->update(['is_active' => false]);

        return redirect()->route('officers.index', $organization)
            ->with('flash', ['message' => 'Officer deactivated.']);
    }
}
