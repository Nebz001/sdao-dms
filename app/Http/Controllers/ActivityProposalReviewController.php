<?php

namespace App\Http\Controllers;

use App\ActivityProposals\Exceptions\ProposalVenueConflictException;
use App\ActivityProposals\ReviewActivityProposal;
use App\Approval\ApproverQueue;
use App\Approval\SectionFlags;
use App\Approval\StepApproverResolver;
use App\Attachments\AttachmentSlots;
use App\Calendar\VenueConflictChecker;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\ReviewQueueFilter;
use App\Enums\Sdg;
use App\Http\Controllers\Concerns\HandlesReviewActions;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use App\Support\CurrentPeriod;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityProposalReviewController extends Controller
{
    use HandlesReviewActions;

    /**
     * No filter (or ?filter=overdue): the live queue — InReview proposals
     * where the actor is the current-step approver, exactly as before,
     * optionally narrowed to the ones waiting past ApproverQueue's
     * threshold. Any other recognized filter value switches to this
     * approver's own decision history for the current academic year — see
     * historyRows(). An unrecognized value quietly falls back to the live
     * queue rather than erroring, so every KPI card link is safe to click.
     */
    public function index(Request $request, ApproverQueue $queue): Response
    {
        $filter = ReviewQueueFilter::tryFrom((string) $request->query('filter', ''));
        $user = Auth::user();

        if ($filter?->isHistory()) {
            $rows = $this->historyRows($user, $filter);
        } else {
            $documents = $queue->pendingFor($user, FormType::ActivityProposal, ['activityProposal']);

            if ($filter === ReviewQueueFilter::Overdue) {
                $documents = $documents->filter(ApproverQueue::isOverdue(...))->values();
            }

            $rows = $documents->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'current_step_position' => $d->current_step_position,
                'calendar_mode' => $d->activityProposal?->calendar_mode->value,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
                'waiting_since' => ApproverQueue::waitingSince($d),
                'wait_tier' => ApproverQueue::waitTier($d),
                'decision' => null,
            ])->values()->all();
        }

        return Inertia::render('review/activity-proposals/index', [
            'queue' => $rows,
            'filter' => $filter?->value,
            'filterLabel' => $filter?->label(),
            'academicYear' => CurrentPeriod::get()->academicYear,
        ]);
    }

    /**
     * This approver's own decision transitions (Approved/Returned/Rejected,
     * per $filter->actions()) on Activity Proposal documents, this academic
     * year — the "history" side of the ?filter= modes. Each row keeps the
     * live queue's shape (so the page can render either kind uniformly) plus
     * a `decision` block and the document's REAL current status, since a
     * historical document is very likely no longer InReview.
     *
     * Returns a plain array (not a Collection) so its return type can carry
     * an accurate PHPDoc shape without fighting Collection's non-covariant
     * TValue generic — a plain array<int, array{...}> return type accepts a
     * more specific literal shape than declared, a Collection<int, ...> one
     * doesn't.
     *
     * @return array<int, array{
     *     id: int, title: string, status: string, current_step_position: int|null,
     *     calendar_mode: string|null, organization: array{id: int, name: string},
     *     created_at: CarbonInterface|null, waiting_since: null, wait_tier: null,
     *     decision: array{action: string, decided_at: CarbonInterface},
     * }>
     */
    private function historyRows(User $user, ReviewQueueFilter $filter): array
    {
        [$yearStart, $yearEnd] = CurrentPeriod::get()->academicYearRange();

        return DocumentTransition::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', array_map(fn ($a) => $a->value, $filter->actions()))
            ->where('created_at', '>=', $yearStart)
            ->where('created_at', '<', $yearEnd)
            ->whereHas('document', fn ($q) => $q->where('form_type', FormType::ActivityProposal->value))
            ->with(['document.organization', 'document.activityProposal'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (DocumentTransition $t) {
                $d = $t->document;

                return [
                    'id' => $d->id,
                    'title' => $d->title,
                    'status' => $d->status->value,
                    'current_step_position' => $d->current_step_position,
                    'calendar_mode' => $d->activityProposal?->calendar_mode->value,
                    'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                    'created_at' => $d->created_at,
                    'waiting_since' => null,
                    'wait_tier' => null,
                    'decision' => ['action' => $t->action->value, 'decided_at' => $t->created_at],
                ];
            })
            ->values()
            ->all();
    }

    public function show(Document $document, VenueConflictChecker $checker, StepApproverResolver $resolver): Response
    {
        Gate::authorize('reviewView', $document);

        $document->load(['organization', 'activityProposal.calendarActivity', 'transitions.actor', 'stepApprovals.user', 'workflowTemplate.steps', 'attachments']);

        $proposal = $document->activityProposal;
        $activity = $proposal?->calendarActivity;
        $attachments = AttachmentSlots::presentForDocument($document);
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
                // Group E backlog — Activity Description restored.
                'activity_description' => $proposal->activity_description,
                // Exact field corrections (Phase 2 item 7 slice 4b).
                'criteria_mechanics' => $proposal->criteria_mechanics,
                'program_flow' => $proposal->program_flow,
                // Itemized expenses (client request, post-Part-2); `expenses`
                // is the legacy free-text fallback for pre-existing
                // proposals that never got rows — see the model docblock.
                'expense_items' => $proposal->expense_items,
                'expense_items_total' => $proposal->expenseItemsTotal,
                'expenses' => $proposal->expenses,
                // Group E backlog — typed in directly by the officer, not
                // sourced from org membership.
                'responsible_persons' => $proposal->responsible_persons,
                'proposed_budget' => $proposal->proposed_budget,
                // Exact field corrections (Phase 2 item 7 slice 4a).
                // Group B item 5 — these accessors fold in the "Others" free text.
                'activity_nature_label' => $proposal->activityNatureLabel,
                'activity_type_label' => $proposal->activityTypeLabel,
                'partner_organizations' => $proposal->partner_organizations,
                // Multi-select (Group C item 1) — one label per selected goal.
                'target_sdg_labels' => $proposal->target_sdg?->map(fn (Sdg $s) => $s->label())->values()->all() ?? [],
                // Closed dropdown (Group C item 2).
                'budget_source_label' => $proposal->budget_source?->label(),
            ] : null,
            'activity' => $activity ? [
                'name' => $activity->name,
                'venue' => $activity->venue,
                'activity_date' => $activity->activity_date->toDateString(),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'history' => $document->transitions->map(fn ($t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'from_status' => $t->from_status?->value,
                'to_status' => $t->to_status->value,
                'step_position' => $t->step_position,
                'comment' => $t->comment,
                'flagged_sections' => $t->flagged_sections,
                'section_comments' => $t->section_comments,
                'field_changes' => $t->field_changes,
                'actor' => $t->actor ? ['name' => $t->actor->name] : null,
                'created_at' => $t->created_at,
            ]),
            'flaggedSectionLabels' => SectionFlags::labelsFor($document->form_type),
            'sectionFlags' => SectionFlags::for($document->form_type),
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
            // Group E item 1 — whether the viewer is still an approver of the
            // CURRENT step, not merely whether they hold an approval row on
            // it. `hasApproved` alone only stays meaningful for a step whose
            // quorum survives a single approval (SDAO's required_approvals =
            // 2): a single-approval role's own approve() call immediately
            // advances current_step_position, so hasApproved re-evaluates
            // against the NEW step (where they never approved) and would
            // otherwise flip back to false — reopening the Approve button for
            // someone DocumentPolicy::review() no longer authorizes to use
            // it. Reuses the same `review` ability the action endpoints
            // already gate on (HandlesReviewActions::authorizeReviewAction()),
            // so "can the action card render" and "can this POST succeed"
            // can never disagree.
            'canAct' => Gate::allows('review', $document),
            'currentStepRole' => $step?->role?->value,
            'requiredApprovals' => $step?->required_approvals ?? 1,
            'activityConflict' => $activityConflict,
            'hasConfirmedConflict' => $hasConfirmedConflict,
        ]);
    }

    public function approve(Document $document, ReviewActivityProposal $action): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-proposals.index')) {
            return $stale;
        }

        try {
            // Off-calendar venue-conflict race re-check lives inside the
            // shared action now — see ReviewActivityProposal::approve().
            if ($stale = $this->runReviewAction(fn () => $action->approve($document, Auth::user()), 'review.activity-proposals.index')) {
                return $stale;
            }
        } catch (ProposalVenueConflictException $e) {
            return redirect()->route('review.activity-proposals.show', $document)
                ->withErrors(['approve' => $e->getMessage()]);
        }

        if ($document->current_step_position === null) {
            // This was the finalizing approval — the document has no current
            // step anymore, so DocumentPolicy::review() would 403 a redirect
            // back to .show. Send the approver to the queue instead.
            return redirect()->route('review.activity-proposals.index')
                ->with('flash', ['message' => 'Proposal approved.']);
        }

        return redirect()->route('review.activity-proposals.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ReviewActivityProposal $action): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-proposals.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(
            fn () => $action->reject($document, Auth::user(), $request->string('comment')->toString() ?: null),
            'review.activity-proposals.index',
        )) {
            return $stale;
        }

        return redirect()->route('review.activity-proposals.index')
            ->with('flash', ['message' => 'Proposal rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ReviewActivityProposal $action): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.activity-proposals.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $action->returnForRevision(
            $document,
            Auth::user(),
            $request->string('comment')->toString() ?: null,
            $request->input('sections'),
            $request->input('section_comments'),
        ), 'review.activity-proposals.index')) {
            return $stale;
        }

        return redirect()->route('review.activity-proposals.show', $document)
            ->with('flash', ['message' => 'Proposal returned for revision.']);
    }
}
