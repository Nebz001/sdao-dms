<?php

namespace App\Http\Controllers;

use App\ActivityProposals\ResubmitActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\ActivityProposals\UpdateProposalDraft;
use App\Calendar\VenueConflictChecker;
use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use App\Enums\Term;
use App\Http\Requests\Proposals\ConflictCheckRequest;
use App\Http\Requests\Proposals\StoreProposalStepOneRequest;
use App\Http\Requests\Proposals\SubmitProposalRequest;
use App\Http\Requests\Proposals\UpdateActivityProposalRequest;
use App\Http\Requests\Proposals\UpdateProposalDraftRequest;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\OrganizationMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityProposalController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $documents = Document::query()
            ->with(['organization', 'activityProposal.calendarActivity'])
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('submitted_by', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'calendar_mode' => $d->activityProposal?->calendar_mode->value,
                'form_step' => $d->activityProposal?->form_step,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
            ]);

        return Inertia::render('activity-proposals/index', ['proposals' => $documents]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return Inertia::render('activity-proposals/create', [
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
            'calendarModes' => collect(ProposalCalendarMode::cases())->map(fn ($m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
            // Exact field corrections (Phase 2 item 7 slice 4a).
            'activityNatures' => collect(ActivityNature::cases())->map(fn ($n) => [
                'value' => $n->value,
                'label' => $n->label(),
            ]),
            'activityTypes' => collect(ActivityType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'sdgs' => collect(Sdg::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->number().'. '.$s->label(),
            ]),
        ]);
    }

    public function store(StoreProposalStepOneRequest $request, StartProposalDraft $action): RedirectResponse
    {
        $user = Auth::user();
        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('submit', $membership->organization);

        $mode = ProposalCalendarMode::from($request->string('calendar_mode')->toString());

        $document = $action->execute(
            actor: $user,
            organization: $membership->organization,
            mode: $mode,
            data: $request->validated(),
        );

        return redirect()->route('activity-proposals.continue', $document);
    }

    /**
     * Live off-calendar conflict preview (single activity, read-only).
     */
    public function conflictCheck(ConflictCheckRequest $request, VenueConflictChecker $checker): JsonResponse
    {
        $venue = $request->string('venue')->toString();
        $date = $request->string('activity_date')->toString();
        $start = $request->string('start_time')->toString();
        $end = $request->string('end_time')->toString();

        $confirmed = $checker->confirmedConflicts($venue, $date, $start, $end)
            ->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values();

        $tentative = $checker->tentativeConflicts($venue, $date, $start, $end)
            ->map(fn ($c) => [
                'name' => $c->name,
                'venue' => $c->venue,
                'activity_date' => $c->activity_date->toDateString(),
                'start_time' => $c->start_time,
                'end_time' => $c->end_time,
                'organization' => $c->calendar->document->organization->name,
            ])->values();

        return response()->json(['confirmed' => $confirmed, 'tentative' => $tentative]);
    }

    /**
     * JSON list of the actor's org's Approved CalendarActivities for the on-calendar picker.
     */
    public function onCalendarActivities(): JsonResponse
    {
        $user = Auth::user();

        $membership = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $membership) {
            return response()->json(['activities' => []]);
        }

        $activities = CalendarActivity::query()
            ->whereHas('calendar.document', fn ($q) => $q
                ->where('organization_id', $membership->organization_id)
                ->where('status', DocumentStatus::Approved->value))
            ->orderBy('activity_date')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'venue' => $a->venue,
                'activity_date' => $a->activity_date->toDateString(),
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
            ]);

        return response()->json(['activities' => $activities]);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['organization', 'activityProposal.calendarActivity', 'transitions.actor', 'stepApprovals.user']);

        $proposal = $document->activityProposal;
        $activity = $proposal?->calendarActivity;

        return Inertia::render('activity-proposals/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
            ],
            'proposal' => $proposal ? [
                'id' => $proposal->id,
                'calendar_mode' => $proposal->calendar_mode->value,
                'title' => $proposal->title,
                'objectives' => $proposal->objectives,
                'narrative' => $proposal->narrative,
                'proposed_budget' => $proposal->proposed_budget,
                'form_step' => $proposal->form_step,
                // Exact field corrections (Phase 2 item 7 slice 4a).
                'activity_nature_label' => $proposal->activity_nature?->label(),
                'activity_type_label' => $proposal->activity_type?->label(),
                'partner_organizations' => $proposal->partner_organizations,
                'target_sdg_label' => $proposal->target_sdg?->label(),
                'budget_source' => $proposal->budget_source,
            ] : null,
            'activity' => $activity ? [
                'id' => $activity->id,
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
        ]);
    }

    /**
     * Step-1 re-edit form (Returned documents only).
     */
    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['organization', 'activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;
        $activity = $proposal?->calendarActivity;

        return Inertia::render('activity-proposals/edit', [
            'document' => ['id' => $document->id, 'title' => $document->title],
            'proposal' => $proposal ? [
                'calendar_mode' => $proposal->calendar_mode->value,
                'title' => $proposal->title,
                'objectives' => $proposal->objectives,
                'narrative' => $proposal->narrative,
                'proposed_budget' => $proposal->proposed_budget,
                // Exact field corrections (Phase 2 item 7 slice 4a) — raw
                // values for re-selecting in the editable form.
                'activity_nature' => $proposal->activity_nature?->value,
                'activity_type' => $proposal->activity_type?->value,
                'partner_organizations' => $proposal->partner_organizations,
                'target_sdg' => $proposal->target_sdg?->value,
                'budget_source' => $proposal->budget_source,
            ] : null,
            'activity' => $activity ? [
                'id' => $activity->id,
                'name' => $activity->name,
                'venue' => $activity->venue,
                'activity_date' => $activity->activity_date->toDateString(),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
            ] : null,
            'terms' => collect(Term::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'activityNatures' => collect(ActivityNature::cases())->map(fn ($n) => [
                'value' => $n->value,
                'label' => $n->label(),
            ]),
            'activityTypes' => collect(ActivityType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'sdgs' => collect(Sdg::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->number().'. '.$s->label(),
            ]),
        ]);
    }

    /**
     * Step-2 narrative form (Draft documents — continue after step 1).
     */
    public function continue(Document $document): Response
    {
        if ($document->submitted_by !== Auth::id() || $document->status !== DocumentStatus::Draft) {
            abort(403);
        }

        $document->load(['organization', 'activityProposal.calendarActivity']);
        $proposal = $document->activityProposal;
        $activity = $proposal?->calendarActivity;

        return Inertia::render('activity-proposals/step-two', [
            'document' => ['id' => $document->id, 'title' => $document->title],
            'proposal' => $proposal ? [
                'calendar_mode' => $proposal->calendar_mode->value,
                'title' => $proposal->title,
                'objectives' => $proposal->objectives,
                'narrative' => $proposal->narrative,
                // proposed_budget is read-only here — set once at step 1
                // (Phase 2 item 7 slice 4a), never re-collected at step 2.
                'proposed_budget' => $proposal->proposed_budget,
                'budget_source' => $proposal->budget_source,
            ] : null,
            'activity' => $activity ? [
                'name' => $activity->name,
                'venue' => $activity->venue,
                'activity_date' => $activity->activity_date->toDateString(),
                'start_time' => $activity->start_time,
                'end_time' => $activity->end_time,
            ] : null,
        ]);
    }

    /**
     * Auto-save step-2 narrative fields (debounced, idempotent, never enters chain).
     */
    public function draft(UpdateProposalDraftRequest $request, Document $document, UpdateProposalDraft $action): JsonResponse
    {
        if ($document->submitted_by !== Auth::id() || $document->status !== DocumentStatus::Draft) {
            abort(403);
        }

        $action->execute(Auth::user(), $document, $request->validated());

        return response()->json(['saved' => true]);
    }

    /**
     * Submit Draft proposal to the approval chain (step-2 completion).
     */
    public function submit(SubmitProposalRequest $request, Document $document, SubmitActivityProposal $action): RedirectResponse
    {
        if ($document->submitted_by !== Auth::id() || $document->status !== DocumentStatus::Draft) {
            abort(403);
        }

        // Chain-entry submit bypasses OrganizationMembershipService (there's
        // no org membership lookup on this route), so the account-verified
        // gate isn't inherited automatically the way it is for the other four
        // forms — assert it explicitly here.
        if (! Auth::user()->isVerifiedAccount()) {
            abort(403);
        }

        $result = $action->execute(
            actor: Auth::user(),
            document: $document,
            objectives: $request->string('objectives')->toString(),
            narrative: $request->string('narrative')->toString(),
        );

        $flash = ['message' => 'Proposal submitted for review.'];

        if ($result['warnings'] !== []) {
            $flash['warnings'] = $result['warnings'];
        }

        return redirect()->route('activity-proposals.show', $result['document'])
            ->with('flash', $flash);
    }

    /**
     * Edit and resubmit a Returned proposal.
     */
    public function update(UpdateActivityProposalRequest $request, Document $document, ResubmitActivityProposal $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $result = $action->execute(
            actor: Auth::user(),
            document: $document,
            data: $request->validated(),
        );

        $flash = ['message' => 'Proposal resubmitted for review.'];

        if ($result['warnings'] !== []) {
            $flash['warnings'] = $result['warnings'];
        }

        return redirect()->route('activity-proposals.show', $result['document'])
            ->with('flash', $flash);
    }
}
