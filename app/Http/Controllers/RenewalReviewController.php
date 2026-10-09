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
use App\Models\School;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RenewalReviewController extends Controller
{
    use HandlesReviewActions;

    public function index(): Response
    {
        $queue = new ReviewQueueData(
            FormType::OrganizationRenewal,
            'review.renewals.show',
            fn (Document $d) => $d->organization->school?->name ?? School::NONE_LABEL,
            ['organization.school'],
        );

        // Stats and recent decisions are deferred (skeletons on the page);
        // the pending rows stay immediate. Authorization lives in
        // ReviewQueueData (review / reviewView abilities).
        return Inertia::render('review/renewals/index', [
            'queue' => $queue->queue(),
            'extraColumnLabel' => 'College',
            'stats' => Inertia::defer(fn () => $queue->stats(Auth::user()), 'queue-insights'),
            'recent' => Inertia::defer(fn () => $queue->recent(Auth::user()), 'queue-insights'),
        ]);
    }

    public function show(Document $document, DocumentViewData $viewData): Response
    {
        Gate::authorize('reviewView', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail.adviser', 'transitions.actor', 'stepApprovals.user', 'attachments']);

        $detail = $document->registrationDetail;
        $attachments = AttachmentSlots::presentForDocument($document);
        $user = Auth::user();

        // Which SDAO members have already approved the current step?
        $currentStepApprovals = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->map(fn ($a) => ['user_id' => $a->user_id, 'name' => $a->user->name]);

        $myApproval = $document->stepApprovals
            ->where('step_position', $document->current_step_position)
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('review/renewals/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'organization' => [
                    'id' => $document->organization->id,
                    'name' => $document->organization->name,
                    // Field-presence parity (Phase 2 item 7 slice 2).
                    'college' => $document->organization->school?->name,
                    'program' => $document->organization->program?->name,
                ],
            ],
            'detail' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'organization_type_label' => $detail->organization_type->label(),
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
                'adviser' => $detail->adviser ? ['name' => $detail->adviser->name] : null,
                'academic_year' => $detail->academic_year,
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'view' => $viewData->for($document, Auth::user(), $document->organization->name, [['label' => 'College', 'value' => $document->organization->school?->name], ['label' => 'Academic year', 'value' => $detail?->academic_year]]),
            'sectionFlags' => SectionFlags::for($document->form_type),
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
            // Group E item 1 — see RegistrationReviewController::show() for
            // why this is reused here even though it's a no-op today.
            'canAct' => Gate::allows('review', $document),
        ]);
    }

    public function approve(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.renewals.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $engine->approve($document, Auth::user(), $request->string('comment')->toString() ?: null), 'review.renewals.index')) {
            return $stale;
        }

        if ($document->current_step_position === null) {
            // This was the finalizing approval — the document has no current
            // step anymore, so DocumentPolicy::review() would 403 a redirect
            // back to .show. Send the approver to the queue instead.
            return redirect()->route('review.renewals.index')
                ->with('flash', FlashToast::make('Renewal approved', 'The organization is covered for the renewed academic year.', actions: [FlashToast::link('View organization', route('admin.organizations.show', $document->organization_id))]));
        }

        return redirect()->route('review.renewals.show', $document)
            ->with('flash', FlashToast::make('Approval recorded', 'Your approval is saved. The document moves on once every required approver at this step has approved.'));
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.renewals.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(
            fn () => $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null),
            'review.renewals.index',
        )) {
            return $stale;
        }

        return redirect()->route('review.renewals.index')
            ->with('flash', FlashToast::make('Renewal rejected', 'The renewal is closed. The organization must file a new one.'));
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        if ($stale = $this->authorizeReviewAction(Auth::user(), $document, 'review.renewals.index')) {
            return $stale;
        }

        if ($stale = $this->runReviewAction(fn () => $engine->returnForRevision(
            $document,
            Auth::user(),
            $request->string('comment')->toString() ?: null,
            $request->input('sections'),
            $request->input('section_comments'),
        ), 'review.renewals.index')) {
            return $stale;
        }

        return redirect()->route('review.renewals.show', $document)
            ->with('flash', FlashToast::make('Returned for revision', 'The submitter was notified. It returns to you once they resubmit.'));
    }
}
