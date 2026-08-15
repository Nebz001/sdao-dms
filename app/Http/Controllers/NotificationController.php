<?php

namespace App\Http\Controllers;

use App\Support\NotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-state actions for the notification bell (shared prop lives in
 * HandleInertiaRequests::share()), plus the full, filterable destination
 * behind the bell's "View all notifications" link.
 * HandleInertiaRequests::notificationsFor() is a tight, capped teaser (8
 * rows); this is where genuine browsing of a user's notification history
 * happens — same capped-teaser-vs-full-page split as
 * AdminDashboardController::recentActivity() vs ActivityLogController.
 *
 * All three actions scope through the authenticated user's own
 * `notifications()` relation rather than binding a DatabaseNotification
 * directly off the route — that makes "not mine" and "doesn't exist" the
 * same 404, instead of leaking whether another user's notification id
 * exists.
 */
class NotificationController extends Controller
{
    private const int PER_PAGE = 15;

    /**
     * @var list<string>
     */
    private const array VALID_STATUSES = ['unread', 'read'];

    public function index(Request $request): Response
    {
        // An unrecognized status value is treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // ActivityLogController::index().
        $status = $request->string('status')->toString();
        $status = in_array($status, self::VALID_STATUSES, true) ? $status : 'all';

        $notifications = $request->user()->notifications()
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            // Unread-first (Postgres sorts NULL read_at first on DESC),
            // newest-first within each group — same ordering as the bell's
            // capped teaser, just not capped here.
            ->orderByDesc('read_at')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            'notifications' => [
                'data' => collect($notifications->items())
                    ->map(fn ($notification) => NotificationPresenter::present($notification))
                    ->values(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                    'total' => $notifications->total(),
                ],
                'links' => [
                    'prev' => $notifications->previousPageUrl(),
                    'next' => $notifications->nextPageUrl(),
                ],
            ],
            'filters' => [
                'status' => $status,
            ],
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $notification): HttpResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        // A 204, not back() — this is fired from a plain, un-awaited fetch()
        // (see useNotificationRead's markRowRead), not an Inertia visit, and
        // a redirect response here is actively harmful: fetch() follows a
        // 302 preserving the original PATCH method (browsers only downgrade
        // to GET on 301/303, not 302, for non-GET/HEAD methods), so the
        // followed request re-hits whatever page the fetch originated from
        // with PATCH and 405s. Harmless in practice (the response is never
        // read), but it's needless noise on every mark-as-read click.
        return response()->noContent();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('flash', ['message' => 'All notifications marked as read.']);
    }
}
