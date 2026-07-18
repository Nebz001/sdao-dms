<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PendingAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = User::query()
            ->where('account_status', AccountStatus::Unverified->value)
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'created_at' => $u->created_at,
            ]);

        return Inertia::render('admin/pending-accounts/index', ['accounts' => $accounts]);
    }

    public function verify(User $account, VerifyAccount $action): RedirectResponse
    {
        $action->execute(Auth::user(), $account);

        return redirect()->route('admin.pending-accounts.index')
            ->with('flash', ['message' => "{$account->name}'s account has been verified."]);
    }

    public function reject(User $account, RejectAccount $action): RedirectResponse
    {
        $action->execute(Auth::user(), $account);

        return redirect()->route('admin.pending-accounts.index')
            ->with('flash', ['message' => "{$account->name}'s account has been rejected."]);
    }
}
