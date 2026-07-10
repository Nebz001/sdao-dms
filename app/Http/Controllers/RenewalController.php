<?php

namespace App\Http\Controllers;

use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Http\Requests\Renewals\StoreRenewalRequest;
use App\Http\Requests\Renewals\UpdateRenewalRequest;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Renewals\SubmitOrganizationRenewal;
use App\Renewals\UpdateOrganizationRenewal;
use App\Support\AcademicYear;
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
            ->with('organization')
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
            ]);

        return Inertia::render('renewals/index', ['renewals' => $documents]);
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

        if ($membership === null) {
            return Inertia::render('renewals/create', [
                'membership' => null,
                'priorRecord' => null,
                'alreadyRenewed' => false,
                'academicYear' => AcademicYear::current(),
                'organizationTypes' => $organizationTypes,
            ]);
        }

        $priorRecord = $renewalAction->mostRecentApprovedRecord($membership->organization);
        $academicYear = AcademicYear::current();
        $alreadyRenewed = $renewalAction->hasNonRejectedRenewal($membership->organization, $academicYear);

        $detail = $priorRecord?->registrationDetail;

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
                'roster' => $detail->roster,
            ] : null,
            'alreadyRenewed' => $alreadyRenewed,
            'academicYear' => $academicYear,
            'organizationTypes' => $organizationTypes,
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
            organizationType: OrganizationType::from($request->string('organization_type')->toString()),
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            roster: $request->input('roster'),
        );

        return redirect()->route('renewals.show', $document)
            ->with('flash', ['message' => 'Renewal submitted for SDAO review.']);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail.adviser', 'transitions.actor', 'stepApprovals.user']);

        $detail = $document->registrationDetail;

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
                'roster' => $detail->roster,
                'academic_year' => $detail->academic_year,
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

    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail']);
        $detail = $document->registrationDetail;

        return Inertia::render('renewals/edit', [
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
                'organization_type' => $detail->organization_type->value,
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
                'roster' => $detail->roster,
            ] : null,
            'organizationTypes' => collect(OrganizationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function update(UpdateRenewalRequest $request, Document $document, UpdateOrganizationRenewal $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $action->execute(
            actor: Auth::user(),
            document: $document,
            organizationType: OrganizationType::from($request->string('organization_type')->toString()),
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            roster: $request->input('roster'),
        );

        return redirect()->route('renewals.show', $document)
            ->with('flash', ['message' => 'Renewal resubmitted for SDAO review.']);
    }
}
