<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Http\Requests\Organizations\BindOfficerRequest;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
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

    public function index(Request $request, Organization $organization): Response
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

        $search = $request->string('search')->trim()->toString();

        // Candidates the adviser can bind: never an account holding an
        // approver role (RoleAssignment is the only thing that knows an
        // account is an adviser/chair/dean/etc. — OrganizationMembership has
        // no concept of it), must be SDAO-Verified (BindOrganizationOfficer
        // rejects an unverified/rejected student anyway — filtered here too
        // so the adviser never sees an un-bindable candidate), AND either a
        // bare account (no OrganizationMembership row at all — the shape a
        // self-registered student has) OR an account currently ACTIVE in THIS
        // org. Using is_active (not mere row existence) means a former
        // officer whose membership was deactivated on turnover is correctly
        // excluded, not perpetually "known."
        // One organization per student (Phase 2 item 4): also hide anyone with
        // an in-flight (Draft/InReview/Returned) registration for a DIFFERENT
        // org — they'd immediately trip BindOrganizationOfficer's/
        // SubmitOrganizationRegistration's guards anyway, so the adviser
        // never sees an un-bindable candidate in the picker.
        $inFlightElsewhereUserIds = Document::query()
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('organization_id', '!=', $organization->id)
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->pluck('submitted_by');

        $students = User::query()
            ->whereDoesntHave('roleAssignments', fn ($q) => $q->where('role', '!=', Role::Student->value))
            ->where('account_status', AccountStatus::Verified->value)
            ->where(function ($query) use ($organization) {
                $query->whereDoesntHave('organizationMemberships')
                    ->orWhereHas('organizationMemberships', fn ($q) => $q
                        ->where('organization_id', $organization->id)
                        ->active()
                    );
            })
            ->whereNotIn('id', $inFlightElsewhereUserIds)
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
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
        Gate::authorize('manageOfficers', $organization);

        $membership->update(['is_active' => false]);

        return redirect()->route('officers.index', $organization)
            ->with('flash', ['message' => 'Officer deactivated.']);
    }
}
