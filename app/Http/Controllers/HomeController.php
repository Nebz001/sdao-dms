<?php

namespace App\Http\Controllers;

use App\Models\CalendarActivity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The public landing page. Guests see it; authenticated users are
     * bounced to their dashboard before it ever renders.
     *
     * The `upcomingActivities` prop is a genuinely new, independent query
     * path — it does not reuse CalendarController (which deliberately
     * includes InReview/"tentative" activities for the authenticated
     * double-booking-prevention view). This is guest-facing, so only
     * `CalendarActivity::approved()` rows may ever appear here.
     */
    public function index(): RedirectResponse|Response
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('welcome', [
            'upcomingActivities' => $this->upcomingActivities(),
        ]);
    }

    /**
     * Approved activities over the next 90 days, capped at 50 rows. The
     * window is generous on purpose: it needs to cover every remaining day
     * of the currently-displayed month (for the mini calendar's dots) as
     * well as the next several activities chronologically (for the list),
     * even when the current month itself is sparse or already over.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingActivities(): array
    {
        return CalendarActivity::query()
            ->approved()
            ->whereDate('activity_date', '>=', now()->toDateString())
            ->whereDate('activity_date', '<=', now()->addDays(90)->toDateString())
            ->with('calendar.document.organization')
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->limit(50)
            ->get()
            ->map(fn (CalendarActivity $activity) => [
                'id' => $activity->id,
                'name' => $activity->name,
                'venue' => $activity->venue,
                'activity_date' => $activity->activity_date->toDateString(),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
                'organization' => $activity->calendar->document->organization->name,
            ])
            ->values()
            ->all();
    }
}
