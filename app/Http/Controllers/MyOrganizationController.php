<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use App\Organizations\OrganizationStatusResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MyOrganizationController extends Controller
{
    /**
     * The caller's own organization — status, requirements checklist,
     * officers, adviser, and coverage. No Gate: authorization IS the query
     * (query-scope-as-authorization idiom, see
     * DocumentHistoryController::index()'s docblock) — a caller with no
     * active membership has no organization to show, full stop, and is sent
     * back to their dashboard rather than shown a 403 for a page that isn't
     * "theirs" to be denied from in the first place. One-org-per-student
     * (Phase 2 item 4) means this is always at most a single organization,
     * never a list.
     */
    public function show(OrganizationStatusResolver $statusResolver): Response|RedirectResponse
    {
        $user = Auth::user();

        $membership = $user->organizationMemberships()->active()->with('organization')->first();

        if ($membership === null) {
            return redirect()->route('dashboard');
        }

        $organization = $membership->organization;
        $result = $statusResolver->for($organization);

        $officers = OrganizationMembership::query()
            ->with('user:id,name,email')
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get()
            ->map(fn (OrganizationMembership $m) => [
                'id' => $m->id,
                'user' => ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email],
                'position' => $m->position->value,
                'position_label' => $m->position->label(),
            ])
            ->values();

        $adviser = $organization->adviser?->user;

        return Inertia::render('organizations/mine', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'school' => $organization->school?->name,
                'program' => $organization->program?->name,
            ],
            'status' => $result->status->value,
            'renewalDue' => $result->renewalDue,
            'coversThroughAcademicYear' => $result->coversThroughAcademicYear,
            'requirements' => $result->requirements->toArray(),
            'officers' => $officers,
            'adviser' => $adviser !== null
                ? ['id' => $adviser->id, 'name' => $adviser->name, 'email' => $adviser->email]
                : null,
        ]);
    }
}
