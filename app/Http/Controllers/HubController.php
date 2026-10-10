<?php

namespace App\Http\Controllers;

use App\Organizations\StudentDashboardData;
use App\Support\CurrentPeriod;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hub pages a student reaches from the home cards (Submit, My Documents,
 * Review). The options on each hub come from the same client nav config the
 * home cards use, which already holds back anything the user may not open, so
 * there is nothing to authorize here beyond being signed in. The only data
 * the server adds is the status chips on the Submit options, read from the
 * student's own organization (see StudentDashboardData::hubChips()).
 */
class HubController extends Controller
{
    public function submit(): Response
    {
        return $this->render('submit', withChips: true);
    }

    public function myDocuments(): Response
    {
        return $this->render('my-documents');
    }

    public function review(): Response
    {
        return $this->render('review');
    }

    private function render(string $hub, bool $withChips = false): Response
    {
        $membership = Auth::user()->organizationMemberships()->active()->with('organization')->first();

        return Inertia::render('hubs/show', [
            'hub' => $hub,
            'organizationName' => $membership?->organization->name,
            'chips' => $withChips && $membership !== null
                ? StudentDashboardData::for($membership, CurrentPeriod::get())->hubChips()
                : (object) [],
        ]);
    }
}
