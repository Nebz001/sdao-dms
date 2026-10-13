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
     * The `activities` prop reads the same publication rule as the
     * authenticated calendar, `CalendarActivity::published()`: only
     * activities of fully approved Activity Proposals ever reach a guest (an
     * approved Activity Calendar alone publishes nothing), and only the safe
     * public fields below, never a description. The full published set (past
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
            ->published()
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
