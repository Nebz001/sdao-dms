<?php

namespace App\Http\Controllers;

use App\Approval\DocumentViewData;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Requests\Reports\UpdateReportRequest;
use App\Models\ActivityProposal;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Reports\SubmitAfterActivityReport;
use App\Reports\UpdateAfterActivityReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AfterActivityReportController extends Controller
{
    /**
     * List: reports belonging to any org the user is an active officer of
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
            ->where('form_type', FormType::AfterActivityReport->value)
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

        return Inertia::render('reports/index', ['reports' => $documents]);
    }

    /**
     * The picker source: approved proposals for the user's org that do not
     * already have a live (non-rejected) report. Two independent conditions —
     * (i) current org only, (ii) no non-rejected report — are NOT conflated:
     * a proposal whose only report was Rejected reappears here.
     */
    public function create(): Response
    {
        $user = Auth::user();

        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($membership === null) {
            return Inertia::render('reports/create', [
                'membership' => null,
                'eligibleProposals' => [],
            ]);
        }

        $eligibleProposals = SubmitAfterActivityReport::reportableProposalsFor($membership->organization)
            ->with('activityProposal.calendarActivity')
            ->get()
            ->map(fn (Document $d) => [
                'activity_proposal_id' => $d->activityProposal->id,
                'title' => $d->activityProposal->title,
                'activity' => $d->activityProposal->calendarActivity ? [
                    'name' => $d->activityProposal->calendarActivity->name,
                    'venue' => $d->activityProposal->calendarActivity->venue,
                    'activity_date' => $d->activityProposal->calendarActivity->activity_date->toDateString(),
                ] : null,
            ]);

        return Inertia::render('reports/create', [
            'membership' => [
                'id' => $membership->id,
                'position' => $membership->position->value,
                'position_label' => $membership->position->label(),
                'organization' => [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                ],
            ],
            'eligibleProposals' => $eligibleProposals,
            'attachmentSlots' => AttachmentSlots::slotsFor(FormType::AfterActivityReport),
        ]);
    }

    public function store(StoreReportRequest $request, SubmitAfterActivityReport $action): RedirectResponse
    {
        $user = Auth::user();

        $proposal = ActivityProposal::query()
            ->with('document.organization')
            ->findOrFail($request->integer('activity_proposal_id'));

        Gate::authorize('submit', $proposal->document->organization);

        $document = $action->execute(
            actor: $user,
            proposal: $proposal,
            summary: $request->string('summary')->toString(),
            outcomes: $request->string('outcomes')->toString() ?: null,
            participantCount: $request->filled('participant_count') ? $request->integer('participant_count') : null,
            activityChairs: $request->input('activity_chairs'),
            preparedBy: $request->string('prepared_by')->toString(),
            eventProgram: $request->string('event_program')->toString(),
            targetParticipantsPercentage: $request->integer('target_participants_percentage'),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::AfterActivityReport),
        );

        return redirect()->route('reports.show', $document)
            ->with('flash', ['message' => 'After-activity report submitted for SDAO review.']);
    }

    public function show(Document $document, DocumentViewData $viewData): Response
    {
        Gate::authorize('view', $document);

        $document->load([
            'organization',
            'afterActivityReport.activityProposal.calendarActivity',
            'transitions.actor',
            'stepApprovals.user',
            'attachments',
        ]);

        $report = $document->afterActivityReport;
        $attachments = AttachmentSlots::presentForDocument($document);

        return Inertia::render('reports/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
                // Date Submitted (Phase 2 item 7 slice 3) — derived, same
                // pattern as Activity Calendar's Date Received.
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
                    // Name of Event / Date and Time of Event — derived from
                    // the linked proposal/activity, not duplicated storage.
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
        ]);
    }

    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['afterActivityReport.activityProposal.calendarActivity', 'attachments']);
        $report = $document->afterActivityReport;
        $attachments = AttachmentSlots::presentForDocument($document);
        $flaggedSections = SectionFlags::currentlyFlagged($document);

        return Inertia::render('reports/edit', [
            'flaggedComment' => SectionFlags::currentComment($document),
            'flaggedSectionComments' => SectionFlags::currentSectionComments($document),
            'document' => ['id' => $document->id, 'title' => $document->title],
            'detail' => $report ? [
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
            'flaggedSections' => $flaggedSections,
        ]);
    }

    public function update(UpdateReportRequest $request, Document $document, UpdateAfterActivityReport $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $action->execute(
            actor: Auth::user(),
            document: $document,
            summary: $request->string('summary')->toString(),
            outcomes: $request->string('outcomes')->toString() ?: null,
            participantCount: $request->filled('participant_count') ? $request->integer('participant_count') : null,
            activityChairs: $request->input('activity_chairs'),
            preparedBy: $request->string('prepared_by')->toString(),
            eventProgram: $request->string('event_program')->toString(),
            targetParticipantsPercentage: $request->integer('target_participants_percentage'),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::AfterActivityReport),
        );

        return redirect()->route('reports.show', $document)
            ->with('flash', ['message' => 'Report resubmitted for SDAO review.']);
    }
}
