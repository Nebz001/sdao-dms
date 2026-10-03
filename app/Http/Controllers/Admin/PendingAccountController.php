<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\PendingAccountStats;
use App\Http\Controllers\Controller;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\RevertAccountReview;
use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use App\Support\CurrentPeriod;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PendingAccountController extends Controller
{
    public function index(PendingAccountStats $stats): Response
    {
        $queue = $stats->queue();

        return Inertia::render('admin/pending-accounts/index', [
            'accounts' => $queue,
            'buckets' => $stats->buckets($queue),
            // First of an oldest-first list; null when nothing is pending.
            'oldest' => $queue->first(),
            // Term-scoped aggregates scan every self-registration, so they
            // load after the queue and its cards have rendered.
            'termActivity' => Inertia::defer(fn () => $stats->termActivity(CurrentPeriod::get())),
        ]);
    }

    public function verify(User $account, VerifyAccount $action): RedirectResponse
    {
        $action->execute(Auth::user(), $account);

        return redirect()->route('admin.pending-accounts.index')
            ->with('flash', FlashToast::make(
                'Account verified',
                "{$account->name} can now submit documents and be bound as an officer.",
                actions: [FlashToast::undo(route('admin.pending-accounts.revert', $account))],
            ));
    }

    public function reject(User $account, RejectAccount $action): RedirectResponse
    {
        $action->execute(Auth::user(), $account);

        return redirect()->route('admin.pending-accounts.index')
            ->with('flash', FlashToast::make(
                'Account rejected',
                "{$account->name} can no longer submit documents. The account was kept, not deleted.",
                actions: [FlashToast::undo(route('admin.pending-accounts.revert', $account))],
            ));
    }

    public function revert(User $account, RevertAccountReview $action): RedirectResponse
    {
        try {
            $action->execute(Auth::user(), $account);
        } catch (ValidationException $e) {
            return redirect()->route('admin.pending-accounts.index')
                ->with('flash', FlashToast::error('Review can’t be undone', $e->errors()['account'][0]));
        }

        return redirect()->route('admin.pending-accounts.index')
            ->with('flash', FlashToast::make(
                'Review undone',
                "{$account->name} is back in the pending queue.",
            ));
    }
}
