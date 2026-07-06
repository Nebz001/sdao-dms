<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\CalendarActivity;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Shared venue calendar — confirmed (Approved) and tentative (InReview) activities
     * across all orgs, for the double-booking-prevention view.
     */
    public function index(): Response
    {
        $activities = CalendarActivity::query()
            ->whereHas('calendar.document', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Approved->value,
                DocumentStatus::InReview->value,
            ]))
            ->with('calendar.document.organization')
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (CalendarActivity $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'venue' => $a->venue,
                'activity_date' => $a->activity_date->toDateString(),
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
                'status' => $a->calendar->document->status->value,
                'organization' => $a->calendar->document->organization->name,
                'document_id' => $a->calendar->document_id,
            ]);

        return Inertia::render('calendar/index', [
            'activities' => $activities,
        ]);
    }
}
