<?php

namespace App\Http\Resources\Mobile;

use App\Enums\TransitionAction;
use App\Models\Document;
use Carbon\CarbonInterface;

/**
 * documents.updated_at is not touched by a partial SDAO approval (see
 * Document::save() guards), so it cannot stand in for "the last thing that
 * happened" — both timestamps below are always read from the transition
 * log instead. Expects ->transitions already eager-loaded (ordered by id
 * ascending, per Document::transitions()); never issues its own query.
 */
class ProposalTimestamps
{
    public static function submittedAt(Document $document): ?CarbonInterface
    {
        return $document->transitions
            ->first(fn ($t) => $t->action === TransitionAction::Submitted)
            ?->created_at;
    }

    public static function updatedAt(Document $document): ?CarbonInterface
    {
        return $document->transitions->last()?->created_at;
    }
}
