<?php

namespace App\Http\Controllers;

use App\Calendar\SubmitActivityCalendar;
use App\Calendar\UpdateActivityCalendar;
use App\Calendar\VenueConflictChecker;
use App\Enums\FormType;
use App\Enums\Term;
use App\Http\Requests\Calendar\ConflictCheckRequest;
use App\Http\Requests\Calendar\StoreActivityCalendarRequest;
use App\Http\Requests\Calendar\UpdateActivityCalendarRequest;
use App\Models\Document;
use App\Models\OrganizationMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityCalendarController extends Controller
{
    /**
     * List: activity calendars belonging to any org the user is an active officer of
     * (both president and secretary see the same list — equal partners).
     */
    public function index(): Response
    {
        $user = Auth::user();

        $organizationIds = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $documents = Document::query()
            ->with('organization')
            ->where('form_type', FormType::ActivityCalendar->value)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
            ]);

        return Inertia::render('activity-calendars/index', ['calendars' => $documents]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return Inertia::render('activity-calendars/create', [
            'membership' => $membership ? [
                'id' => $membership->id,
                'position' => $membership->position->value,
                'position_label' => $membership->position->label(),
                'organization' => [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                ],
            ] : null,
            'terms' => collect(Term::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreActivityCalendarRequest $request, SubmitActivityCalendar $action): RedirectResponse
    {
        $user = Auth::user();
        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('submit', $membership->organization);

        $result = $action->execute(
            actor: $user,
            organization: $membership->organization,
            term: Term::from($request->string('term')->toString()),
            activities: $request->input('activities'),
        );

        $flash = ['message' => 'Activity calendar submitted for SDAO review.'];

        if ($result['warnings'] !== []) {
            $flash['warnings'] = $result['warnings'];
        }

        return redirect()->route('activity-calendars.show', $result['document'])
            ->with('flash', $flash);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['organization', 'activityCalendar.activities', 'transitions.actor', 'stepApprovals.user']);

        $calendar = $document->activityCalendar;

        return Inertia::render('activity-calendars/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
            ],
            'calendar' => $calendar ? [
                'academic_year' => $calendar->academic_year,
                'term' => $calendar->term->value,
                'term_label' => $calendar->term->label(),
                'activities' => $calendar->activities->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'description' => $a->description,
                    'venue' => $a->venue,
                    'activity_date' => $a->activity_date->toDateString(),
                    'start_time' => $a->start_time,
                    'end_time' => $a->end_time,
                ]),
            ] : null,
            'history' => $document->transitions->map(fn ($t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'from_status' => $t->from_status?->value,
                'to_status' => $t->to_status->value,
                'step_position' => $t->step_position,
                'comment' => $t->comment,
                'actor' => $t->actor ? ['name' => $t->actor->name] : null,
                'created_at' => $t->created_at,
            ]),
        ]);
    }

    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['organization', 'activityCalendar.activities']);
        $calendar = $document->activityCalendar;

        return Inertia::render('activity-calendars/edit', [
            'document' => ['id' => $document->id, 'title' => $document->title],
            'calendar' => $calendar ? [
                'term' => $calendar->term->value,
                'activities' => $calendar->activities->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'description' => $a->description,
                    'venue' => $a->venue,
                    'activity_date' => $a->activity_date->toDateString(),
                    'start_time' => $a->start_time,
                    'end_time' => $a->end_time,
                ]),
            ] : null,
            'terms' => collect(Term::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function update(UpdateActivityCalendarRequest $request, Document $document, UpdateActivityCalendar $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $result = $action->execute(
            actor: Auth::user(),
            document: $document,
            term: Term::from($request->string('term')->toString()),
            activities: $request->input('activities'),
        );

        $flash = ['message' => 'Activity calendar resubmitted for SDAO review.'];

        if ($result['warnings'] !== []) {
            $flash['warnings'] = $result['warnings'];
        }

        return redirect()->route('activity-calendars.show', $result['document'])
            ->with('flash', $flash);
    }

    /**
     * Live preview: returns confirmed + tentative conflicts per submitted activity slot.
     * Read-only — no writes.
     */
    public function conflictCheck(ConflictCheckRequest $request, VenueConflictChecker $checker): JsonResponse
    {
        $results = [];

        foreach ($request->input('activities') as $activity) {
            $confirmed = $checker->confirmedConflicts(
                $activity['venue'],
                $activity['activity_date'],
                $activity['start_time'],
                $activity['end_time'],
            )->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values();

            $tentative = $checker->tentativeConflicts(
                $activity['venue'],
                $activity['activity_date'],
                $activity['start_time'],
                $activity['end_time'],
            )->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values();

            $results[] = [
                'confirmed' => $confirmed,
                'tentative' => $tentative,
            ];
        }

        return response()->json(['results' => $results]);
    }
}
