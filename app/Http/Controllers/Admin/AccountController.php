<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Identity\Admin\DeactivateAccount;
use App\Identity\Admin\ReactivateAccount;
use App\Models\User;
use App\Support\FlashToast;
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
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return response()->json(['accounts' => $approvers->rows($accounts)]);
    }

    public function deactivate(Request $request, User $account, DeactivateAccount $action): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $action->execute(Auth::user(), $account, $request->input('reason'));

        return back()->with('flash', FlashToast::make(
            'Account deactivated',
            "{$account->name} was signed out everywhere and can no longer log in.",
            actions: [FlashToast::undo(route('admin.accounts.reactivate', $account))],
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
