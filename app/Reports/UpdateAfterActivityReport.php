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
        string $narrative,
        ?string $outcomes = null,
        ?int $participantCount = null,
    ): Document {
        if ($document->status !== DocumentStatus::Returned) {
            throw new AuthorizationException('Only returned documents can be edited.');
        }

        if ($document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the original submitter may edit this document.');
        }

        return DB::transaction(function () use ($actor, $document, $narrative, $outcomes, $participantCount) {
            // activity_proposal_id is intentionally NOT included here — the
            // hard link to the approved activity never changes on revision.
            $document->afterActivityReport()->update([
                'narrative' => $narrative,
                'outcomes' => $outcomes,
                'participant_count' => $participantCount,
            ]);

            $this->engine->resubmit($document, $actor);
            $document->refresh();

            return $document;
        });
    }
}
