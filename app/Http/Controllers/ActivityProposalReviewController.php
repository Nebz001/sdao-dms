<?php

namespace App\Http\Controllers;

use App\Approval\ApprovalEngine;
use App\Approval\StepApproverResolver;
use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityProposalReviewController extends Controller
{
    /**
     * Queue: InReview proposals where the actor is the current-step approver.
     */
    public function index(StepApproverResolver $resolver): Response
    {
        $user = Auth::user();

        $documents = Document::query()
            ->with(['organization', 'activityProposal', 'workflowTemplate.steps'])
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('status', DocumentStatus::InReview->value)
            ->orderBy('created_at')
            ->get()
            ->filter(function (Document $d) use ($user, $resolver) {
                try {
                    $step = $d->workflowTemplate?->steps
                        ->firstWhere('position', $d->current_step_position);

                    return $step && $resolver->approversFor($step, $d)->contains('id', $user->id);
                } catch (\Throwable) {
                    return false;
                }
            })
            ->values()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'current_step_position' => $d->current_step_position,
                'calendar_mode' => $d->activityProposal?->calendar_mode->value,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
            ]);

        return Inertia::render('review/activity-proposals/index', ['queue' => $documents]);
    }

    public function show(Document $document, VenueConflictChecker $checker, StepApproverResolver $resolver): Response
    {
        Gate::authorize('review', $document);

        $document->load(['organization', 'activityProposal.calendarActivity', 'transitions.actor', 'stepApprovals.user', 'workflowTemplate.steps']);

        $proposal = $document->activityProposal;
        $activity = $proposal?->calendarActivity;
        $user = Auth::user();

        $step = $document->workflowTemplate?->steps
            ->firstWhere('position', $document->current_step_position);

        $currentStepApprovals = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->map(fn ($a) => ['user_id' => $a->user_id, 'name' => $a->user->name]);

        $myApproval = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->where('user_id', $user->id)
            ->first();

        // Off-calendar conflict state for the approve button.
        $activityConflict = null;
        $hasConfirmedConflict = false;

        if ($proposal?->calendar_mode === ProposalCalendarMode::OffCalendar && $activity !== null) {
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

            $activityConflict = ['confirmed' => $confirmed];
            $hasConfirmedConflict = count($confirmed) > 0;
        }

        return Inertia::render('review/activity-proposals/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
            ],
            'proposal' => $proposal ? [
                'calendar_mode' => $proposal->calendar_mode->value,
                'title' => $proposal->title,
                'objectives' => $proposal->objectives,
                'narrative' => $proposal->narrative,
                // Exact field corrections (Phase 2 item 7 slice 4b).
                'criteria_mechanics' => $proposal->criteria_mechanics,
                'program_flow' => $proposal->program_flow,
                'source_of_funding' => $proposal->source_of_funding,
                'expenses' => $proposal->expenses,
                'proposed_budget' => $proposal->proposed_budget,
                // Exact field corrections (Phase 2 item 7 slice 4a).
                'activity_nature_label' => $proposal->activity_nature?->label(),
                'activity_type_label' => $proposal->activity_type?->label(),
                'partner_organizations' => $proposal->partner_organizations,
                'target_sdg_label' => $proposal->target_sdg?->label(),
                'budget_source' => $proposal->budget_source,
            ] : null,
            'activity' => $activity ? [
                'name' => $activity->name,
                'venue' => $activity->venue,
                'activity_date' => $activity->activity_date->toDateString(),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
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
            'currentStepRole' => $step?->role?->value,
            'requiredApprovals' => $step?->required_approvals ?? 1,
            'activityConflict' => $activityConflict,
            'hasConfirmedConflict' => $hasConfirmedConflict,
        ]);
    }

    public function approve(Document $document, ApprovalEngine $engine, VenueConflictChecker $checker): RedirectResponse
    {
        Gate::authorize('review', $document);

        // Race re-check for off-calendar: a rival proposal may have been Approved since this one
        // entered review, claiming the same venue/date/time slot.
        $document->load('activityProposal.calendarActivity');
        $proposal = $document->activityProposal;

        if ($proposal?->calendar_mode === ProposalCalendarMode::OffCalendar) {
            $activity = $proposal->calendarActivity;

            if ($activity !== null) {
                $conflicts = $checker->confirmedConflicts(
                    $activity->venue,
                    $activity->activity_date->toDateString(),
                    $activity->start_time,
                    $activity->end_time,
                    $document->id,
                );

                if ($conflicts->isNotEmpty()) {
                    $name = $conflicts->first()->name;

                    return redirect()->route('review.activity-proposals.show', $document)
                        ->withErrors(['approve' => "Cannot approve: \"{$name}\" at {$activity->venue} now conflicts with an already-approved booking. Return the document to the submitter to resolve."]);
                }
            }
        }

        $engine->approve($document, Auth::user());

        return redirect()->route('review.activity-proposals.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null);

        return redirect()->route('review.activity-proposals.index')
            ->with('flash', ['message' => 'Proposal rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->returnForRevision($document, Auth::user(), $request->string('comment')->toString() ?: null);

        return redirect()->route('review.activity-proposals.show', $document)
            ->with('flash', ['message' => 'Proposal returned for revision.']);
    }
}
