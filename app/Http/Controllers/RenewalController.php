<?php

namespace App\Http\Controllers;

use App\Approval\CurrentApproverLabel;
use App\Approval\DocumentViewData;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Http\Requests\Renewals\StoreRenewalRequest;
use App\Http\Requests\Renewals\UpdateRenewalRequest;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Organizations\StudentFormAvailability;
use App\Renewals\SubmitOrganizationRenewal;
use App\Renewals\UpdateOrganizationRenewal;
use App\Support\CurrentPeriod;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RenewalController extends Controller
{
    /**
     * List: renewals belonging to any org the user is an active officer of
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
            ->with(['organization', 'workflowTemplate.steps'])
            ->where('form_type', FormType::OrganizationRenewal->value)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status->value,
                'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                'created_at' => $d->created_at,
                'current_approver' => CurrentApproverLabel::for($d),
            ]);

        return Inertia::render('renewals/index', ['renewals' => $documents,
            'canStart' => StudentFormAvailability::for($user, FormType::OrganizationRenewal),
        ]);
    }

    public function create(SubmitOrganizationRenewal $renewalAction): Response
    {
        $user = Auth::user();

        $membership = OrganizationMembership::query()
            ->with(['organization.school', 'organization.program'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $organizationTypes = collect(OrganizationType::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->label(),
        ]);
        $currentPeriod = CurrentPeriod::get();
        $currentPeriodProp = [
            'academic_year' => $currentPeriod->academicYear,
            'term' => $currentPeriod->term->value,
            'label' => $currentPeriod->label(),
        ];

        if ($membership === null) {
            return Inertia::render('renewals/create', [
                'membership' => null,
                'priorRecord' => null,
                'eligibility' => ['status' => null, 'message' => null],
                'currentPeriod' => $currentPeriodProp,
                'organizationTypes' => $organizationTypes,
                'attachmentSlots' => AttachmentSlots::slotsFor(FormType::OrganizationRenewal),
            ]);
        }

        $eligibility = $renewalAction->eligibilityFor($membership->organization);
        $detail = $eligibility->priorRecord?->registrationDetail;

        return Inertia::render('renewals/create', [
            'membership' => [
                'id' => $membership->id,
                'position' => $membership->position->value,
                'position_label' => $membership->position->label(),
                'organization' => [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                    // Field-presence parity (Phase 2 item 7 slice 2).
                    'college' => $membership->organization->school?->name,
                    'program' => $membership->organization->program?->name,
                ],
            ],
            'priorRecord' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
            ] : null,
            'eligibility' => [
                'status' => $eligibility->status->value,
                'message' => $eligibility->message(),
            ],
            'currentPeriod' => $currentPeriodProp,
            'organizationTypes' => $organizationTypes,
            'attachmentSlots' => AttachmentSlots::slotsFor(FormType::OrganizationRenewal),
        ]);
    }

    public function store(StoreRenewalRequest $request, SubmitOrganizationRenewal $action): RedirectResponse
    {
        $user = Auth::user();
        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('submit', $membership->organization);

        $document = $action->execute(
            actor: $user,
            organization: $membership->organization,
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::OrganizationRenewal),
        );

        return redirect()->route('renewals.show', $document)
            ->with('flash', FlashToast::make('Renewal submitted', 'SDAO will review it. You will be notified of the decision.'));
    }

    public function show(Document $document, DocumentViewData $viewData): Response
    {
        Gate::authorize('view', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail.adviser', 'transitions.actor', 'stepApprovals.user', 'attachments']);

        $detail = $document->registrationDetail;
        $attachments = AttachmentSlots::presentForDocument($document);

        return Inertia::render('renewals/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
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
        ]);
    }

    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail', 'attachments']);
        $detail = $document->registrationDetail;
        $attachments = AttachmentSlots::presentForDocument($document);
        $flaggedSections = SectionFlags::currentlyFlagged($document);

        return Inertia::render('renewals/edit', [
            'flaggedComment' => SectionFlags::currentComment($document),
            'flaggedSectionComments' => SectionFlags::currentSectionComments($document),
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'organization' => [
                    'name' => $document->organization->name,
                    // Field-presence parity (Phase 2 item 7 slice 2).
                    'college' => $document->organization->school?->name,
                    'program' => $document->organization->program?->name,
                ],
            ],
            'detail' => $detail ? [
                // organization_type is no longer editable here (structural
                // fix, 2026-09-09 plan) — computed once at creation from the
                // org's school_id and frozen forever after, so only its label
                // is shown, read-only.
                'organization_type_label' => $detail->organization_type->label(),
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'flaggedSections' => $flaggedSections,
        ]);
    }

    public function update(UpdateRenewalRequest $request, Document $document, UpdateOrganizationRenewal $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $action->execute(
            actor: Auth::user(),
            document: $document,
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::OrganizationRenewal),
        );

        return redirect()->route('renewals.show', $document)
            ->with('flash', FlashToast::make('Renewal resubmitted', 'SDAO has your changes and will review them again.'));
    }
}
