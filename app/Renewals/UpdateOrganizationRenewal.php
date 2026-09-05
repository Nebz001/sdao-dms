<?php

namespace App\Renewals;

use App\Approval\ApprovalEngine;
use App\Approval\FieldChangeSet;
use App\Approval\SectionFields;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateOrganizationRenewal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly AttachmentStorage $attachmentStorage,
        private readonly OrganizationMembershipService $membershipService,
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

        if (! $this->membershipService->canActOnDocument($actor, $document)) {
            throw new AuthorizationException('Only an active officer of this organization may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $organizationType, $purposeOfOrganization,
            $contactPerson, $contactNo, $emailAddress, $dateOrganized, $attachmentFiles
        ) {
            // Field-level revision diffs — see UpdateOrganizationRegistration
            // for the full rationale. Note SectionFields has no
            // 'adviser_selection' entry for Renewal: SectionFlags exposes the
            // key (shared match arm with Registration) but the renewal form
            // has no adviser field, so flagging it finds zero fields.
            $flagged = SectionFlags::currentlyFlagged($document);
            $trackedFields = $flagged === []
                ? []
                : SectionFields::definitionsForSections($document->form_type, $flagged);
            $oldValues = $trackedFields === []
                ? []
                : FieldChangeSet::snapshot($document->registrationDetail, $trackedFields);
            // Same "before" idea, for whichever flagged keys are attachment
            // slots rather than scalar-field sections — must also run before
            // this resubmit's storeMany() call below.
            $hadAttachmentsBefore = $flagged === []
                ? []
                : FieldChangeSet::snapshotAttachmentPresence($document, $flagged);

            // academic_year, term, and covers_academic_year are intentionally
            // NOT included: they are set once at creation
            // (SubmitOrganizationRenewal) and must never change across the
            // return/resubmit cycle — covers_academic_year in particular is
            // the uniqueness key hasNonRejectedRenewalCovering() matches on.
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

            $fieldChanges = $flagged === [] ? null : FieldChangeSet::build(
                $document->form_type,
                $flagged,
                $oldValues,
                FieldChangeSet::snapshot($document->registrationDetail()->first(), $trackedFields),
                $hadAttachmentsBefore,
                $attachmentFiles,
            );

            $this->engine->resubmit($document, $actor, $fieldChanges);
            $document->refresh();

            return $document;
        });
    }
}
