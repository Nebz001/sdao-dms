<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Http\UploadedFile;
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
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     *
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        string $name,
        ?int $schoolId,
        ?int $programId,
        int $adviserId,
        string $purposeOfOrganization,
        string $contactPerson,
        string $contactNo,
        string $emailAddress,
        string $dateOrganized,
        array $attachmentFiles = [],
    ): Document {
        // organization_type is derived from school_id (structural fix,
        // 2026-09-09 plan) — no longer a caller-supplied value, so there is
        // nothing left to cross-check it against. The one invariant that
        // survives, reframed in terms of school_id/program_id alone: a
        // regular-school org needs a program. Enforced at the FormRequest
        // layer too (StoreRegistrationRequest), but this action is directly
        // callable — DemoDataSeeder is proof, it's how every bad-shape org in
        // this codebase's history was created — so the invariant belongs here
        // as the real boundary, not only at the HTTP layer. A caller/
        // programmer bug, not user input: \InvalidArgumentException, not
        // ValidationException.
        if ($schoolId !== null && $programId === null) {
            $isSeniorHigh = School::where('id', $schoolId)->where('type', 'senior_high')->exists();

            if (! $isSeniorHigh) {
                throw new \InvalidArgumentException('An organization at a regular school must have a program.');
            }
        }

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
            $actor, $name, $schoolId, $programId, $adviserId,
            $purposeOfOrganization, $contactPerson, $contactNo, $emailAddress,
            $dateOrganized, $academicYear, $attachmentFiles
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
                'organization_type' => OrganizationType::fromSchoolId($schoolId)->value,
                'purpose_of_organization' => $purposeOfOrganization,
                'contact_person' => $contactPerson,
                'contact_no' => $contactNo,
                'email_address' => $emailAddress,
                'date_organized' => $dateOrganized,
                'adviser_id' => $adviserId,
            ]);

            // Phase 2 item 8 — every attachment listed on the client's real
            // form is required, no conditionals on Organization Type.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
