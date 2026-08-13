<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Read-state actions for the notification bell (shared prop lives in
 * HandleInertiaRequests::share()). Both actions scope through the
 * authenticated user's own `notifications()` relation rather than binding a
 * DatabaseNotification directly off the route — that makes "not mine" and
 * "doesn't exist" the same 404, instead of leaking whether another user's
 * notification id exists.
 */
class NotificationController extends Controller
{
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('flash', ['message' => 'All notifications marked as read.']);
    }
}
