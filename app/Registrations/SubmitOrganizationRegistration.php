<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitOrganizationRegistration
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * @param  array<int, string>|null  $roster
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        OrganizationType $organizationType,
        string $description,
        string $contactPerson,
        string $contactNumber,
        string $contactEmail,
        string $dateOrganized,
        ?array $roster = null,
    ): Document {
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer of this organization to submit a registration.');
        }

        // One organization per student (Phase 2 item 4): block a second,
        // simultaneous in-flight registration for a DIFFERENT org — reachable
        // even with the BindOrganizationOfficer guard in place, since an
        // adviser can independently deactivate a membership while that org's
        // registration document is still open.
        $hasInFlightElsewhere = Document::query()
            ->where('submitted_by', $actor->id)
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('organization_id', '!=', $organization->id)
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->exists();

        if ($hasInFlightElsewhere) {
            throw ValidationException::withMessages([
                'organization' => 'You already have an in-progress registration for a different organization.',
            ]);
        }

        $adviser = $this->roleDirectory->adviserFor($organization);
        $academicYear = AcademicYear::current();

        return DB::transaction(function () use (
            $actor, $organization, $organizationType, $description,
            $contactPerson, $contactNumber, $contactEmail, $dateOrganized,
            $roster, $adviser, $academicYear
        ) {
            $document = Document::create([
                'form_type' => FormType::OrganizationRegistration,
                'variant' => null,
                'title' => "Organization Registration — {$organization->name} ({$academicYear})",
                'status' => DocumentStatus::Draft,
                'current_step_position' => null,
                'organization_id' => $organization->id,
                'workflow_template_id' => null,
                'submitted_by' => $actor->id,
            ]);

            OrganizationRegistrationDetail::create([
                'document_id' => $document->id,
                'organization_type' => $organizationType->value,
                'description' => $description,
                'contact_person' => $contactPerson,
                'contact_number' => $contactNumber,
                'contact_email' => $contactEmail,
                'date_organized' => $dateOrganized,
                'adviser_id' => $adviser->id,
                'roster' => $roster,
            ]);

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
