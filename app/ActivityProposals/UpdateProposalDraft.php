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
            // Exact field corrections (Phase 2 item 7 slice 4b).
            'criteria_mechanics' => $data['criteria_mechanics'] ?? $proposal->criteria_mechanics,
            'program_flow' => $data['program_flow'] ?? $proposal->program_flow,
            'source_of_funding' => $data['source_of_funding'] ?? $proposal->source_of_funding,
            'expenses' => $data['expenses'] ?? $proposal->expenses,
        ]);

        return $document;
    }
}
