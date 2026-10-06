<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountNameRequest;
use App\Identity\Admin\DeactivateAccount;
use App\Identity\Admin\ReactivateAccount;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\FlashToast;
use App\Support\PersonName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Deactivating and reactivating non student accounts, plus the "Find an
 * account" search that lets SDAO reach an account which no longer holds a
 * role (so is not on the Approver Accounts list).
 */
class AccountController extends Controller
{
    private const int SEARCH_LIMIT = 8;

    public function search(Request $request, ApproverController $approvers): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();

        if (mb_strlen($search) < 2) {
            return response()->json(['accounts' => []]);
        }

        $accounts = User::query()
            ->nonStudent()
            ->where(fn ($q) => $q
                ->whereNameMatches($search)
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return response()->json(['accounts' => $approvers->rows($accounts)]);
    }

    /**
     * Corrects a person's first and last name. `name` is rebuilt from them by
     * the User model, so all three always agree.
     */
    public function updateName(UpdateAccountNameRequest $request, User $account): RedirectResponse
    {
        $account->first_name = PersonName::stripTitle($request->string('first_name')->toString());
        $account->last_name = PersonName::clean($request->string('last_name')->toString());
        $account->save();

        return back()->with('flash', FlashToast::make('Name updated', "The account is now named {$account->name}."));
    }

    public function deactivate(Request $request, User $account, DeactivateAccount $action): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        // Read BEFORE deactivating: a student officer's seat ends with the account.
        $seat = OrganizationMembership::query()->where('user_id', $account->id)->active()->with('organization')->first();

        $action->execute(Auth::user(), $account, $request->input('reason'));

        // Reactivating restores the ACCOUNT only. A student's officer seat ended
        // with it and stays ended, so say so rather than letting a bare "Undo"
        // promise more than it does.
        $seatNote = $seat !== null
            ? " Their {$seat->position->label()} seat of {$seat->organization->name} ended and the adviser was told. Reactivating restores the account only, not the seat."
            : '';

        return back()->with('flash', FlashToast::make(
            'Account deactivated',
            "{$account->name} was signed out everywhere and can no longer log in.{$seatNote}",
            actions: [FlashToast::postAction('Reactivate account', route('admin.accounts.reactivate', $account))],
        ));
    }

    public function reactivate(User $account, ReactivateAccount $action): RedirectResponse
    {
        $action->execute(Auth::user(), $account);

        return back()->with('flash', FlashToast::make(
            'Account reactivated',
            "{$account->name} can log in again. Earlier sessions stay signed out.",
            actions: [FlashToast::undo(route('admin.accounts.deactivate', $account))],
        ));
    }
}
