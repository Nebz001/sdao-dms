<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\OfficerChangeStats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\DeclineOfficerChangeRequest;
use App\Models\OfficerChangeRequest;
use App\Organizations\Admin\ApproveOfficerChange;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Support\CurrentPeriod;
use App\Support\FlashToast;
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
    public function index(OfficerChangeStats $stats): Response
    {
        $queue = $stats->queue();

        return Inertia::render('admin/officer-change-requests/index', [
            'requests' => $queue,
            'buckets' => $stats->buckets($queue),
            // First of an oldest-first list; null when nothing is pending.
            'oldest' => $queue->first(),
            'recentDecisions' => $stats->recentlyDecided(),
            // Term-scoped aggregates scan the whole term, so they load after
            // the queue and its cards have rendered.
            'termActivity' => Inertia::defer(fn () => $stats->termActivity(CurrentPeriod::get())),
        ]);
    }

    public function approve(OfficerChangeRequest $officerChangeRequest, ApproveOfficerChange $action): RedirectResponse
    {
        $nomineeName = $officerChangeRequest->nominee->name;
        $positionLabel = $officerChangeRequest->position->label();

        $action->execute(Auth::user(), $officerChangeRequest);

        return redirect()->route('admin.officer-change-requests.index')
            ->with('flash', FlashToast::make('Officer change approved', "{$nomineeName} is now {$positionLabel}."));
    }

    public function decline(OfficerChangeRequest $officerChangeRequest, DeclineOfficerChangeRequest $request, DeclineOfficerChange $action): RedirectResponse
    {
        $nomineeName = $officerChangeRequest->nominee->name;

        $action->execute(Auth::user(), $officerChangeRequest, $request->string('comment')->toString() ?: null);

        return redirect()->route('admin.officer-change-requests.index')
            ->with('flash', FlashToast::make('Request declined', "The request naming {$nomineeName} was declined."));
    }
}
