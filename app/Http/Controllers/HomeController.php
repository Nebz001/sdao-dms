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
     * The `activities` prop is a genuinely new, independent query path — it
     * does not reuse CalendarController (which deliberately includes
     * InReview/"tentative" activities for the authenticated
     * double-booking-prevention view). This is guest-facing, so only
     * `CalendarActivity::approved()` rows may ever appear here. Unlike the
     * old 90-day-forward window this replaced, the full approved set (past
     * and future, unbounded — same convention as CalendarController) is
     * sent down: the mini calendar lets a visitor navigate to any month, so
     * it needs dots available for months outside "the next 90 days" too.
     * The "Next up" list still only shows what's actually upcoming — that
     * filtering happens client-side in PublicActivitiesSection.
     */
    public function index(): RedirectResponse|Response
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('welcome', [
            'activities' => $this->approvedActivities(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedActivities(): array
    {
        return CalendarActivity::query()
            ->approved()
            ->with('calendar.document.organization')
            ->orderBy('activity_date')
            ->orderBy('start_time')
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
