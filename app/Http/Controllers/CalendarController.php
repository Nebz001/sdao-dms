<?php

namespace App\Http\Controllers;

use App\Calendar\CalendarVisibility;
use App\Enums\DocumentStatus;
use App\Models\CalendarActivity;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Venue calendar — confirmed (Approved) and tentative (InReview) activities,
     * limited to what the user may open (see CalendarVisibility). Conflict
     * checking is separate and still sees every booking.
     */
    public function index(CalendarVisibility $visibility): Response
    {
        $user = Auth::user();
        $activities = CalendarActivity::query()
            ->whereHas('calendar.document', fn ($q) => $visibility->restrict($q->whereIn('status', [
                DocumentStatus::Approved->value,
                DocumentStatus::InReview->value,
            ]), $user))
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
            'visibility' => $visibility->scopeFor($user),
        ]);
    }
}
