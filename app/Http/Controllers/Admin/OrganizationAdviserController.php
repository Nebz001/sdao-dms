<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdviserTermOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignOrganizationAdviserRequest;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\Admin\AssignOrganizationAdviser;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * SDAO assigns an existing, unassigned, active adviser from the pool to an
 * organization. The create-a-new-account path (ApproverController::store ->
 * ProvisionApprover) ends in the very same AssignOrganizationAdviser action.
 */
class OrganizationAdviserController extends Controller
{
    public function store(AssignOrganizationAdviserRequest $request, Organization $organization, AssignOrganizationAdviser $action): RedirectResponse
    {
        $incoming = User::query()->findOrFail($request->integer('adviser_id'));

        $change = $action->execute(
            actor: Auth::user(),
            organization: $organization,
            incoming: $incoming,
            outgoingOutcome: AdviserTermOutcome::from($request->string('outgoing_adviser')->toString()),
            verifyOutgoing: true,
            expectedOutgoingUserId: $request->integer('current_adviser_id') ?: null,
        );

        $message = "{$incoming->name} is now the adviser of {$organization->name}.";

        if ($change->outgoing !== null) {
            $message .= $change->outgoingOutcome === AdviserTermOutcome::Deactivated
                ? " {$change->outgoing->name} was deactivated."
                : " {$change->outgoing->name} is back in the adviser pool.";
        }

        return redirect()
            ->route('admin.organizations.show', $organization)
            ->with('flash', FlashToast::make('Adviser assigned', $message));
    }
}
