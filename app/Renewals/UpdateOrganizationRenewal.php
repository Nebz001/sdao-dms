<?php

namespace App\Renewals;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateOrganizationRenewal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     *
     * @throws AuthorizationException
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
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $organizationType, $purposeOfOrganization,
            $contactPerson, $contactNo, $emailAddress, $dateOrganized, $attachmentFiles
        ) {
            // academic_year is intentionally NOT included: it is set once at
            // creation (SubmitOrganizationRenewal) and must never change across
            // the return/resubmit cycle.
            $document->registrationDetail()->update([
                'organization_type' => $organizationType->value,
                'purpose_of_organization' => $purposeOfOrganization,
                'contact_person' => $contactPerson,
                'contact_no' => $contactNo,
                'email_address' => $emailAddress,
                'date_organized' => $dateOrganized,
            ]);

            // Phase 2 item 8 — only newly re-uploaded slots are in
            // $attachmentFiles; untouched slots from the original submission
            // are left in place.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
