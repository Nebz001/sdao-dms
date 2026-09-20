<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeclineOfficerChangeRequest;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SDAO admin review queue for pending officer change requests — same shape
 * as Admin\PendingAccountController. No per-action Gate check here: the
 * `can:access-admin` route middleware already scopes the whole page, and
 * ApproveOfficerChange/DeclineOfficerChange re-assert SDAO membership as
 * defence in depth.
 */
class OfficerChangeReviewController extends Controller
{
    public function index(): Response
    {
        $requests = OfficerChangeRequest::query()
            ->with(['organization', 'requester', 'nominee', 'outgoingOfficer'])
            ->pending()
            ->orderBy('created_at')
            ->get()
            ->map(function (OfficerChangeRequest $r) {
                $currentHolderId = OrganizationMembership::query()
                    ->where('organization_id', $r->organization_id)
                    ->where('position', $r->position->value)
                    ->where('is_active', true)
                    ->value('user_id');

                return [
                    'id' => $r->id,
                    'organization' => ['id' => $r->organization->id, 'name' => $r->organization->name],
                    'position_label' => $r->position->label(),
                    'requester' => ['id' => $r->requester->id, 'name' => $r->requester->name],
                    'nominee' => ['id' => $r->nominee->id, 'name' => $r->nominee->name],
                    'outgoing_officer' => $r->outgoingOfficer ? ['id' => $r->outgoingOfficer->id, 'name' => $r->outgoingOfficer->name] : null,
                    'reason' => $r->reason,
                    'created_at' => $r->created_at,
                    // Whether the seat has changed hands since this request
                    // was filed — surfaced so the admin can decline a stale
                    // request instead of approving into a race.
                    'is_stale' => $currentHolderId !== $r->outgoing_user_id,
                ];
            });

        return Inertia::render('admin/officer-change-requests/index', [
            'requests' => $requests,
        ]);
    }

    public function approve(OfficerChangeRequest $officerChangeRequest, ApproveOfficerChange $action): RedirectResponse
    {
        $nomineeName = $officerChangeRequest->nominee->name;
        $positionLabel = $officerChangeRequest->position->label();

        $action->execute(Auth::user(), $officerChangeRequest);

        return redirect()->route('admin.officer-change-requests.index')
            ->with('flash', ['message' => "{$nomineeName} is now {$positionLabel}."]);
    }

    public function decline(OfficerChangeRequest $officerChangeRequest, DeclineOfficerChangeRequest $request, DeclineOfficerChange $action): RedirectResponse
    {
        $nomineeName = $officerChangeRequest->nominee->name;

        $action->execute(Auth::user(), $officerChangeRequest, $request->string('comment')->toString() ?: null);

        return redirect()->route('admin.officer-change-requests.index')
            ->with('flash', ['message' => "Request naming {$nomineeName} was declined."]);
    }
}
