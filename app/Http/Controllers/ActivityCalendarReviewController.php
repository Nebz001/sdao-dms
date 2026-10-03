<?php

namespace App\Http\Controllers;

use App\Approval\ApprovalEngine;
use App\Approval\DocumentViewData;
use App\Approval\ReviewQueueData;
use App\Calendar\VenueConflictChecker;
use App\Enums\FormType;
use App\Enums\Sdg;
use App\Http\Controllers\Concerns\HandlesReviewActions;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityCalendarReviewController extends Controller
{
    use HandlesReviewActions;

    public function index(): Response
    {
        $queue = new ReviewQueueData(
            FormType::ActivityCalendar,
            'review.activity-calendars.show',
            fn (Document $d) => $d->activityCalendar ? $d->activityCalendar->term->label().', '.$d->activityCalendar->academic_year : null,
            ['activityCalendar'],
        );

        // Stats and recent decisions are deferred (skeletons on the page);
        // the pending rows stay immediate. Authorization lives in
        // ReviewQueueData (review / reviewView abilities).
        return Inertia::render('review/activity-calendars/index', [
            'queue' => $queue->queue(),
            'extraColumnLabel' => 'Period',
            'stats' => Inertia::defer(fn () => $queue->stats(Auth::user()), 'queue-insights'),
            'recent' => Inertia::defer(fn () => $queue->recent(Auth::user()), 'queue-insights'),
        ]);
    }

    public function show(Document $document, VenueConflictChecker $checker, DocumentViewData $viewData): Response
    {
        Gate::authorize('reviewView', $document);

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
                    // Multi-select (Group B item 1) — one label per selected goal.
                    'sdg_labels' => $a->sdg?->map(fn (Sdg $s) => $s->label())->values()->all() ?? [],
                    'participant_program_assigned' => $a->participant_program_assigned,
                    'budget' => $a->budget,
                ]),
            ] : null,
            'view' => $viewData->for($document, Auth::user(), $document->organization->name, [['label' => 'Term', 'value' => $calendar?->term->label()], ['label' => 'Academic year', 'value' => $calendar?->academic_year]]),
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
            // Group E item 1 — see RegistrationReviewController::show() for
            // why this is reused here even though it's a no-op today.
            'canAct' => Gate::allows('review', $document),
            'activityConflicts' => $activityConflicts,
            'hasConfirmedConflict' => $hasConfirmedConflict,
        ]);
    }

    public function approve(Document $document, ApprovalEngine $engine, VenueConflictChecker $checker): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-calendars.index')) {
            return $stale;
        }

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

        if ($stale = $this->runReviewAction(fn () => $engine->approve($document, Auth::user()), 'review.activity-calendars.index')) {
            return $stale;
        }

        if ($document->current_step_position === null) {
            // This was the finalizing approval — the document has no current
            // step anymore, so DocumentPolicy::review() would 403 a redirect
            // back to .show. Send the approver to the queue instead.
            return redirect()->route('review.activity-calendars.index')
                ->with('flash', ['message' => 'Activity calendar approved.']);
        }

        return redirect()->route('review.activity-calendars.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-calendars.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(
            fn () => $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null),
            'review.activity-calendars.index',
        )) {
            return $stale;
        }

        return redirect()->route('review.activity-calendars.index')
            ->with('flash', ['message' => 'Activity calendar rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-calendars.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $engine->returnForRevision(
            $document,
            Auth::user(),
            $request->string('comment')->toString() ?: null,
            $request->input('sections'),
        ), 'review.activity-calendars.index')) {
            return $stale;
        }

        return redirect()->route('review.activity-calendars.show', $document)
            ->with('flash', ['message' => 'Document returned for revision.']);
    }
}
