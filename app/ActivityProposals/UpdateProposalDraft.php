<?php

namespace App\ActivityProposals;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateProposalDraft
{
    /**
     * Auto-save step-2 narrative fields without entering the approval chain.
     * Only callable by the submitter while the document is Draft.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(User $actor, Document $document, array $data): Document
    {
        if ($document->status !== DocumentStatus::Draft || $document->submitted_by !== $actor->id) {
            throw new AuthorizationException('Only the submitter can auto-save their own draft.');
        }

        $proposal = $document->activityProposal;

        // proposed_budget is intentionally NOT autosaved here — it's set
        // once at step 1 (Phase 2 item 7 slice 4a) and is not part of the
        // step-2 narrative autosave.
        $proposal->update([
            'objectives' => $data['objectives'] ?? $proposal->objectives,
            'narrative' => $data['narrative'] ?? $proposal->narrative,
        ]);

        return $document;
    }
}
