<?php

namespace App\ActivityProposals;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateProposalDraft
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /**
     * Auto-save step-2 narrative fields without entering the approval chain.
     * Only callable by an active officer of the org (or the submitter, for
     * the founding-registration edge case — see canActOnDocument()) while
     * the document is Draft.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(User $actor, Document $document, array $data): Document
    {
        if ($document->status !== DocumentStatus::Draft || ! $this->membershipService->canActOnDocument($actor, $document)) {
            throw new AuthorizationException('Only an active officer of this organization can auto-save this draft.');
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
            // Itemized expenses (client request, post-Part-2) — legacy
            // `expenses` prose is intentionally never rewritten here, see
            // App\Models\ActivityProposal's docblock.
            'expense_items' => $data['expense_items'] ?? $proposal->expense_items,
        ]);

        return $document;
    }
}
