<?php

namespace App\Reports;

use App\Approval\ApprovalEngine;
use App\Approval\FieldChangeSet;
use App\Approval\SectionFields;
use App\Approval\SectionFlags;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateAfterActivityReport
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
        string $summary,
        ?string $outcomes = null,
        ?int $participantCount = null,
        ?array $activityChairs = null,
        ?string $preparedBy = null,
        ?string $eventProgram = null,
        ?int $targetParticipantsPercentage = null,
        array $attachmentFiles = [],
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if (! $this->membershipService->canActOnDocument($actor, $document)) {
            throw new AuthorizationException('Only an active officer of this organization may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $summary, $outcomes, $participantCount,
            $activityChairs, $preparedBy, $eventProgram, $targetParticipantsPercentage, $attachmentFiles
        ) {
            // Field-level revision diffs — see UpdateOrganizationRegistration
            // for the full rationale. 'event_details' is intentionally absent
            // from SectionFields: it is a read-only echo of the linked
            // CalendarActivity with zero editable fields, so flagging it
            // produces no diff by design.
            $flagged = SectionFlags::currentlyFlagged($document);
            $trackedFields = $flagged === []
                ? []
                : SectionFields::definitionsForSections($document->form_type, $flagged);
            $oldValues = $trackedFields === []
                ? []
                : FieldChangeSet::snapshot($document->afterActivityReport, $trackedFields);

            // activity_proposal_id is intentionally NOT included here — the
            // hard link to the approved activity never changes on revision.
            $document->afterActivityReport()->update([
                'summary' => $summary,
                'outcomes' => $outcomes,
                'participant_count' => $participantCount,
                'activity_chairs' => $activityChairs,
                'prepared_by' => $preparedBy,
                'event_program' => $eventProgram,
                'target_participants_percentage' => $targetParticipantsPercentage,
            ]);

            // Phase 2 item 8 — only newly re-uploaded slots are in
            // $attachmentFiles; untouched slots from the original submission
            // are left in place.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

            $fieldChanges = $trackedFields === [] ? null : FieldChangeSet::build(
                $document->form_type,
                $flagged,
                $oldValues,
                FieldChangeSet::snapshot($document->afterActivityReport()->first(), $trackedFields),
            );

            $this->engine->resubmit($document, $actor, $fieldChanges);
            $document->refresh();

            return $document;
        });
    }
}
