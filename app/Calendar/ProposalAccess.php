<?php

namespace App\Calendar;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;

/**
 * Whether the venue calendar offers a user the "View proposal" button for an
 * activity. The calendar itself shows every activity to everyone; only this
 * button is gated. The rule, always a subset of what DocumentPolicy::view()
 * and reviewView() allow so the button never leads to the 403 page:
 *
 *  - SDAO: always.
 *  - An officer: documents of every organization they are an active officer of.
 *  - Any other approver: documents they approved or completed themselves, plus
 *    documents currently waiting for their review (DocumentPolicy::review()).
 *  - Anyone else: never.
 */
class ProposalAccess
{
    /**
     * Resolves the user's roles and memberships once, then answers per document.
     *
     * @param  iterable<int, Document>  $documents  the documents about to be listed
     * @return Closure(Document): bool
     */
    public function forUser(User $user, iterable $documents): Closure
    {
        if ($user->roleAssignments->contains(fn ($r) => $r->role === Role::SdaoMember)) {
            return fn (Document $document): bool => true;
        }

        $organizationIds = $user->organizationMemberships()->active()->pluck('organization_id')->all();

        if ($organizationIds !== []) {
            return fn (Document $document): bool => in_array($document->organization_id, $organizationIds, true);
        }

        if (! $user->roleAssignments->contains(fn ($r) => $r->role !== Role::Student)) {
            return fn (Document $document): bool => false;
        }

        $ids = collect($documents)->pluck('id')->all();
        $approvedByUser = DocumentTransition::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', [TransitionAction::Approved->value, TransitionAction::Completed->value])
            ->whereIn('document_id', $ids)
            ->pluck('document_id')
            ->flip();

        return fn (Document $document): bool => $approvedByUser->has($document->id)
            || ($document->status === DocumentStatus::InReview && Gate::forUser($user)->allows('review', $document));
    }
}
