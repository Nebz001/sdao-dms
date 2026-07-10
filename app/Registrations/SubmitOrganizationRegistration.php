<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Founds a brand-new organization (Phase 2 item 5). No pre-existing
 * Organization or OrganizationMembership is required — the student proposes
 * the org and picks an adviser directly. Both the Organization row and the
 * chosen adviser reference exist in a PENDING state from submission onward:
 * the adviser is not actually bound (RoleAssignment.organization_id stays as
 * it was) and the student is not bound as an officer until SDAO Approval
 * (see App\Registrations\ApproveOrganizationRegistration).
 */
class SubmitOrganizationRegistration
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /**
     * @param  array<int, string>|null  $roster
     *
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        string $name,
        int $schoolId,
        ?int $programId,
        int $adviserId,
        OrganizationType $organizationType,
        string $purposeOfOrganization,
        string $contactPerson,
        string $contactNo,
        string $emailAddress,
        string $dateOrganized,
        ?array $roster = null,
    ): Document {
        if (! $actor->isVerifiedAccount()) {
            throw ValidationException::withMessages([
                'organization' => 'Your account has not been SDAO-verified yet.',
            ]);
        }

        // One organization per student (Phase 2 item 4): a founding student
        // has no membership yet, so the membership-based guard alone can't
        // catch a second, simultaneous proposal — this in-flight-document
        // check is now the PRIMARY defense against that.
        if ($this->membershipService->hasActiveMembershipElsewhere($actor)) {
            throw ValidationException::withMessages([
                'organization' => 'You are already an active officer of an organization.',
            ]);
        }

        $hasInFlightProposal = Document::query()
            ->where('submitted_by', $actor->id)
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->exists();

        if ($hasInFlightProposal) {
            throw ValidationException::withMessages([
                'organization' => 'You already have an in-progress organization registration.',
            ]);
        }

        // The chosen adviser must be a real, admin-provisioned adviser
        // account — never free text, never a new account created here.
        $isAdviser = RoleAssignment::query()
            ->where('user_id', $adviserId)
            ->where('role', Role::Adviser->value)
            ->exists();

        if (! $isAdviser) {
            throw ValidationException::withMessages([
                'adviser_id' => 'Choose an adviser from the list of admin-provisioned adviser accounts.',
            ]);
        }

        $academicYear = AcademicYear::current();

        return DB::transaction(function () use (
            $actor, $name, $schoolId, $programId, $adviserId, $organizationType,
            $purposeOfOrganization, $contactPerson, $contactNo, $emailAddress,
            $dateOrganized, $roster, $academicYear
        ) {
            // Pending state (Phase 2 item 5): the org exists from submission
            // onward, but is not "real" until Approved — no adviser
            // RoleAssignment or founding OrganizationMembership exists yet.
            $organization = Organization::create([
                'name' => $name,
                'school_id' => $schoolId,
                'program_id' => $programId,
            ]);

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
                'purpose_of_organization' => $purposeOfOrganization,
                'contact_person' => $contactPerson,
                'contact_no' => $contactNo,
                'email_address' => $emailAddress,
                'date_organized' => $dateOrganized,
                'adviser_id' => $adviserId,
                'roster' => $roster,
            ]);

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
