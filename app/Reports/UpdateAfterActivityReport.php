<?php

namespace App\Reports;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UpdateAfterActivityReport
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /**
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
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use (
            $actor, $document, $summary, $outcomes, $participantCount,
            $activityChairs, $preparedBy, $eventProgram, $targetParticipantsPercentage
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

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
