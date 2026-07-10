<?php

namespace App\Http\Controllers;

use App\Approval\ApprovalEngine;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityCalendarReviewController extends Controller
{
    public function index(): Response
    {
        $documents = Document::query()
            ->with('organization')
            ->where('form_type', FormType::ActivityCalendar->value)
            ->where('status', DocumentStatus::InReview->value)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'current_step_position' => $d->current_step_position,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
            ]);

        return Inertia::render('review/activity-calendars/index', [
            'queue' => $documents,
        ]);
    }

    public function show(Document $document, VenueConflictChecker $checker): Response
    {
        Gate::authorize('review', $document);

        $document->load(['organization', 'activityCalendar.activities', 'transitions.actor', 'stepApprovals.user']);

        $calendar = $document->activityCalendar;
        $user = Auth::user();

        $currentStepApprovals = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->map(fn ($a) => ['user_id' => $a->user_id, 'name' => $a->user->name]);

        $myApproval = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->where('user_id', $user->id)
            ->first();

        // Per-activity conflict state for the review screen (exclude own document)
        $activityConflicts = [];
        if ($calendar) {
            foreach ($calendar->activities as $activity) {
                $confirmed = $checker->confirmedConflicts(
                    $activity->venue,
                    $activity->activity_date->toDateString(),
                    $activity->start_time,
                    $activity->end_time,
                    $document->id,
                )->map(fn ($c) => [
                    'name' => $c->name,
                    'organization' => $c->calendar->document->organization->name,
                ])->values()->all();

                $activityConflicts[$activity->id] = ['confirmed' => $confirmed];
            }
        }

        $hasConfirmedConflict = collect($activityConflicts)->contains(
            fn ($c) => count($c['confirmed']) > 0
        );

        return Inertia::render('review/activity-calendars/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
                // RSO Name / Date Received (Phase 2 item 7 slice 1) — derived,
                // document-level values shown to the approver.
                'rso_name' => $document->organization->name,
                'date_received' => $document->created_at,
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
                    'sdg_label' => $a->sdg?->label(),
                    'participant_program_assigned' => $a->participant_program_assigned,
                    'budget' => $a->budget,
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
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
            'activityConflicts' => $activityConflicts,
            'hasConfirmedConflict' => $hasConfirmedConflict,
        ]);
    }

    public function approve(Document $document, ApprovalEngine $engine, VenueConflictChecker $checker): RedirectResponse
    {
        Gate::authorize('review', $document);

        // Re-check for confirmed conflicts at approve time (race condition: a
        // rival calendar may have been approved since this one was submitted).
        $document->load('activityCalendar.activities');
        $calendar = $document->activityCalendar;

        if ($calendar) {
            $conflictingActivity = null;
            foreach ($calendar->activities as $activity) {
                $conflicts = $checker->confirmedConflicts(
                    $activity->venue,
                    $activity->activity_date->toDateString(),
                    $activity->start_time,
                    $activity->end_time,
                    $document->id,
                );

                if ($conflicts->isNotEmpty()) {
                    $conflictingActivity = $activity;
                    break;
                }
            }

            if ($conflictingActivity !== null) {
                return redirect()->route('review.activity-calendars.show', $document)
                    ->withErrors(['approve' => "Cannot approve: \"{$conflictingActivity->name}\" at {$conflictingActivity->venue} now conflicts with an already-approved booking. Return the document to the submitter to resolve."]);
            }
        }

        $engine->approve($document, Auth::user());

        return redirect()->route('review.activity-calendars.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null);

        return redirect()->route('review.activity-calendars.index')
            ->with('flash', ['message' => 'Activity calendar rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->returnForRevision($document, Auth::user(), $request->string('comment')->toString() ?: null);

        return redirect()->route('review.activity-calendars.show', $document)
            ->with('flash', ['message' => 'Document returned for revision.']);
    }
}
