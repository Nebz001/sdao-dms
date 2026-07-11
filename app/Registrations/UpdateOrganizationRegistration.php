<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationRegistration
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Document $document,
        OrganizationType $organizationType,
        string $purposeOfOrganization,
        string $contactPerson,
        string $contactNo,
        string $emailAddress,
        string $dateOrganized,
        array $attachmentFiles = [],
        ?int $adviserId = null,
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $organizationType, $purposeOfOrganization,
            $contactPerson, $contactNo, $emailAddress, $dateOrganized, $adviserId, $attachmentFiles
        ) {
            $updates = [
                'organization_type' => $organizationType->value,
                'purpose_of_organization' => $purposeOfOrganization,
                'contact_person' => $contactPerson,
                'contact_no' => $contactNo,
                'email_address' => $emailAddress,
                'date_organized' => $dateOrganized,
            ];

            // Return-for-revision preserves the ability to pick a DIFFERENT
            // adviser (Phase 2 item 5) — e.g. SDAO's only issue was the
            // chosen adviser. Re-validated as a real, admin-provisioned
            // adviser account, same as at initial proposal.
            if ($adviserId !== null) {
                $isAdviser = RoleAssignment::query()
                    ->where('user_id', $adviserId)
                    ->where('role', Role::Adviser->value)
                    ->exists();

                if (! $isAdviser) {
                    throw ValidationException::withMessages([
                        'adviser_id' => 'Choose an adviser from the list of admin-provisioned adviser accounts.',
                    ]);
                }

                $updates['adviser_id'] = $adviserId;
            }

            $document->registrationDetail()->update($updates);

            // Phase 2 item 8 — only newly re-uploaded slots are in
            // $attachmentFiles; untouched slots from the original submission
            // are left in place. assertRequiredSlotsFilled checks persisted
            // rows + anything just stored, so completeness still holds.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
