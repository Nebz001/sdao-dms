<?php

namespace App\Reports;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateAfterActivityReport
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

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $summary, $outcomes, $participantCount,
            $activityChairs, $preparedBy, $eventProgram, $targetParticipantsPercentage, $attachmentFiles
        ) {
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

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
