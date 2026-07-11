<?php

namespace App\Http\Controllers;

use App\Approval\ApprovalEngine;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Http\Requests\Review\ReviewActionRequest;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Registrations\ApproveOrganizationRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationReviewController extends Controller
{
    public function index(): Response
    {
        $documents = Document::query()
            ->with('organization')
            ->where('form_type', FormType::OrganizationRegistration->value)
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

        return Inertia::render('review/registrations/index', [
            'queue' => $documents,
        ]);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('review', $document);

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

        // Proactive adviser-availability display (Phase 2 item 5) — mirrors
        // ActivityCalendarReviewController::show()'s pattern of computing
        // conflict state up front so SDAO sees it BEFORE clicking Approve,
        // not only after a failed attempt. The actual enforcement is still
        // the re-check inside ApproveOrganizationRegistration.
        $adviserAvailable = true;
        if ($detail?->adviser_id) {
            $adviserAssignment = RoleAssignment::query()
                ->where('user_id', $detail->adviser_id)
                ->where('role', Role::Adviser->value)
                ->first();

            $adviserAvailable = $adviserAssignment === null
                || $adviserAssignment->organization_id === null
                || $adviserAssignment->organization_id === $document->organization_id;
        }

        return Inertia::render('review/registrations/show', [
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
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'adviserAvailable' => $adviserAvailable,
            'history' => $document->transitions->map(fn ($t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'from_status' => $t->from_status?->value,
                'to_status' => $t->to_status->value,
                'step_position' => $t->step_position,
                'comment' => $t->comment,
                'flagged_sections' => $t->flagged_sections,
                'actor' => $t->actor ? ['name' => $t->actor->name] : null,
                'created_at' => $t->created_at,
            ]),
            // Phase 2 item 9 — resolves the raw flagged_sections keys above
            // into client-facing labels for the Revision History card.
            'flaggedSectionLabels' => SectionFlags::labelsFor($document->form_type),
            'sectionFlags' => SectionFlags::for($document->form_type),
            'currentStepApprovals' => $currentStepApprovals,
            'hasApproved' => $myApproval !== null,
        ]);
    }

    public function approve(Document $document, ApproveOrganizationRegistration $action): RedirectResponse
    {
        Gate::authorize('review', $document);

        // ApproveOrganizationRegistration wraps ApprovalEngine::approve() with
        // the founding-flow race-condition re-check + bind-on-quorum side
        // effects (Phase 2 item 5). A thrown ValidationException redirects
        // back with `errors.approve` via Laravel's default handling, same as
        // any other validation failure.
        $action->execute($document, Auth::user());

        return redirect()->route('review.registrations.show', $document)
            ->with('flash', ['message' => 'Approval recorded.']);
    }

    public function reject(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->reject($document, Auth::user(), $request->string('comment')->toString() ?: null);

        // No membership deactivation needed here (Phase 2 item 5 superseded
        // item 4's original design): a founding registration only ever gets
        // an OrganizationMembership once ApproveOrganizationRegistration sees
        // the SDAO quorum satisfied. A rejected registration never reaches
        // that branch, so there is never a membership to deactivate. This is
        // unlike RenewalReviewController, where the org was already
        // legitimately Approved earlier and the membership is real.

        return redirect()->route('review.registrations.index')
            ->with('flash', ['message' => 'Registration rejected.']);
    }

    public function return(ReviewActionRequest $request, Document $document, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('review', $document);

        $engine->returnForRevision(
            $document,
            Auth::user(),
            $request->string('comment')->toString() ?: null,
            $request->input('sections'),
        );

        return redirect()->route('review.registrations.show', $document)
            ->with('flash', ['message' => 'Document returned for revision.']);
    }
}
