<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationType;
use App\Http\Requests\Registrations\StoreRegistrationRequest;
use App\Http\Requests\Registrations\UpdateRegistrationRequest;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Registrations\SubmitOrganizationRegistration;
use App\Registrations\UpdateOrganizationRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    public function create(): Response
    {
        $user = Auth::user();

        // Find the org this student is an active officer of.
        $membership = OrganizationMembership::query()
            ->with('organization')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return Inertia::render('registrations/create', [
            'membership' => $membership ? [
                'id' => $membership->id,
                'position' => $membership->position->value,
                'position_label' => $membership->position->label(),
                'organization' => [
                    'id' => $membership->organization->id,
                    'name' => $membership->organization->name,
                ],
            ] : null,
            'organizationTypes' => collect(OrganizationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreRegistrationRequest $request, SubmitOrganizationRegistration $action): RedirectResponse
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
            description: $request->string('description')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNumber: $request->string('contact_number')->toString(),
            contactEmail: $request->string('contact_email')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            roster: $request->input('roster'),
        );

        return redirect()->route('registrations.show', $document)
            ->with('flash', ['message' => 'Registration submitted for SDAO review.']);
    }

    public function show(Document $document): Response
    {
        $document->load(['organization', 'registrationDetail.adviser', 'transitions.actor', 'stepApprovals.user']);

        $detail = $document->registrationDetail;

        return Inertia::render('registrations/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
                'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
            ],
            'detail' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'organization_type_label' => $detail->organization_type->label(),
                'description' => $detail->description,
                'contact_person' => $detail->contact_person,
                'contact_number' => $detail->contact_number,
                'contact_email' => $detail->contact_email,
                'date_organized' => $detail->date_organized?->toDateString(),
                'adviser' => $detail->adviser ? ['name' => $detail->adviser->name] : null,
                'roster' => $detail->roster,
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

        $document->load(['organization', 'registrationDetail']);
        $detail = $document->registrationDetail;

        return Inertia::render('registrations/edit', [
            'document' => ['id' => $document->id, 'title' => $document->title],
            'detail' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'description' => $detail->description,
                'contact_person' => $detail->contact_person,
                'contact_number' => $detail->contact_number,
                'contact_email' => $detail->contact_email,
                'date_organized' => $detail->date_organized?->toDateString(),
                'roster' => $detail->roster,
            ] : null,
            'organizationTypes' => collect(OrganizationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function update(UpdateRegistrationRequest $request, Document $document, UpdateOrganizationRegistration $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $action->execute(
            actor: Auth::user(),
            document: $document,
            organizationType: OrganizationType::from($request->string('organization_type')->toString()),
            description: $request->string('description')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNumber: $request->string('contact_number')->toString(),
            contactEmail: $request->string('contact_email')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            roster: $request->input('roster'),
        );

        return redirect()->route('registrations.show', $document)
            ->with('flash', ['message' => 'Registration resubmitted for SDAO review.']);
    }
}
