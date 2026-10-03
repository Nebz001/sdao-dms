<?php

namespace App\Http\Controllers;

use App\Approval\ApprovalEngine;
use App\Approval\DocumentViewData;
use App\Approval\ReviewQueueData;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Http\Controllers\Concerns\HandlesReviewActions;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AfterActivityReportReviewController extends Controller
{
    use HandlesReviewActions;

    public function index(): Response
    {
        $queue = new ReviewQueueData(
            FormType::AfterActivityReport,
            'review.reports.show',
            fn (Document $d) => $d->afterActivityReport?->activityProposal?->title,
            ['afterActivityReport.activityProposal'],
        );

        // Stats and recent decisions are deferred (skeletons on the page);
        // the pending rows stay immediate. Authorization lives in
        // ReviewQueueData (review / reviewView abilities).
        return Inertia::render('review/reports/index', [
            'queue' => $queue->queue(),
            'extraColumnLabel' => 'Activity',
            'stats' => Inertia::defer(fn () => $queue->stats(Auth::user()), 'queue-insights'),
            'recent' => Inertia::defer(fn () => $queue->recent(Auth::user()), 'queue-insights'),
        ]);
    }

    public function show(Document $document, DocumentViewData $viewData): Response
    {
        Gate::authorize('reviewView', $document);

        $document->load([
            'organization',
            'afterActivityReport.activityProposal.calendarActivity',
            'transitions.actor',
            'stepApprovals.user',
            'attachments',
        ]);

        $report = $document->afterActivityReport;
        $attachments = AttachmentSlots::presentForDocument($document);
        $user = Auth::user();

        $currentStepApprovals = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->map(fn ($a) => ['user_id' => $a->user_id, 'name' => $a->user->name]);

        $myApproval = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('review/reports/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
                // Date Submitted (Phase 2 item 7 slice 3) — derived.
                'date_submitted' => $document->created_at,
            ],
            'report' => $report ? [
                'summary' => $report->summary,
                'outcomes' => $report->outcomes,
                'participant_count' => $report->participant_count,
                'activity_chairs' => $report->activity_chairs,
                'prepared_by' => $report->prepared_by,
                'event_program' => $report->event_program,
                'target_participants_percentage' => $report->target_participants_percentage,
                'activity' => $report->activityProposal ? [
                    'title' => $report->activityProposal->title,
                    'venue' => $report->activityProposal->calendarActivity?->venue,
                    'activity_date' => $report->activityProposal->calendarActivity?->activity_date?->toDateString(),
                    'start_time' => $report->activityProposal->calendarActivity?->start_time,
                    'end_time' => $report->activityProposal->calendarActivity?->end_time,
                ] : null,
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'view' => $viewData->for($document, Auth::user(), $report?->activityProposal?->title ?? $document->title, [['label' => 'Organization', 'value' => $document->organization->name], ['label' => 'Event date', 'value' => $report?->activityProposal?->calendarActivity?->activity_date?->format('F j, Y')]]),
            'sectionFlags' => SectionFlags::for($document->form_type),
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
            // Group E item 1 — see RegistrationReviewController::show() for
            // why this is reused here even though it's a no-op today.
            'canAct' => Gate::allows('review', $document),
        ]);
    }

    public function approve(Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.reports.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $engine->approve($document, Auth::user()), 'review.reports.index')) {
            return $stale;
        }

        if ($document->current_step_position === null) {
            // This was the finalizing approval — the document has no current
            // step anymore, so DocumentPolicy::review() would 403 a redirect
            // back to .show. Send the approver to the queue instead.
            return redirect()->route('review.reports.index')
                ->with('flash', ['message' => 'Report approved.']);
        }

        return redirect()->route('review.reports.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.reports.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(
            fn () => $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null),
            'review.reports.index',
        )) {
            return $stale;
        }

        return redirect()->route('review.reports.index')
            ->with('flash', ['message' => 'Report rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.reports.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $engine->returnForRevision(
            $document,
            Auth::user(),
            $request->string('comment')->toString() ?: null,
            $request->input('sections'),
            $request->input('section_comments'),
        ), 'review.reports.index')) {
            return $stale;
        }

        return redirect()->route('review.reports.show', $document)
            ->with('flash', ['message' => 'Document returned for revision.']);
    }
}
