<?php

namespace App\Approval;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\WorkflowStep;

/**
 * The role a document is currently waiting on, for the "Now with: Adviser"
 * line on a student's lists. Read from the document's own template at its
 * current step position, never a name hardcoded here (CLAUDE.md invariant #1).
 * SDAO is two people who must both approve, so it reads as "SDAO".
 *
 * Callers should eager load `workflowTemplate.steps`; without it this does one
 * query per document.
 */
final class CurrentApproverLabel
{
    public static function for(Document $document): ?string
    {
        if (! in_array($document->status, [DocumentStatus::InReview, DocumentStatus::Returned], true)
            || $document->current_step_position === null) {
            return null;
        }

        /** @var WorkflowStep|null $step */
        $step = $document->workflowTemplate?->steps
            ->firstWhere('position', $document->current_step_position);

        if ($step === null) {
            return null;
        }

        return $step->role === Role::SdaoMember ? 'SDAO' : $step->role->label();
    }
}
