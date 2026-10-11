<?php

namespace App\Calendar;

use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whose activities a user sees on the venue calendar and in any list of
 * calendar activities. One rule, applied in the query so other organizations'
 * activities never reach the browser:
 *
 *  - SDAO: every organization.
 *  - An officer: the activities of every organization they are an active
 *    officer of (CLAUDE.md allows one, but the rule does not assume it).
 *  - Any other approver: only activities whose document they personally
 *    approved (an Approved or Completed transition they made).
 *  - Anyone else: nothing.
 *
 * Every document it lets through is one DocumentPolicy::view() also lets the
 * user open (an officer by membership, an approver by hasActedOn()), so a
 * calendar item never leads to the 403 page. The review queue does not use
 * this: a document waiting on an approver stays openable from the queue.
 */
class CalendarVisibility
{
    /** What the user's calendar is scoped to, for the page's empty-state wording. */
    public const string ALL = 'all';

    public const string ORGANIZATION = 'organization';

    public const string APPROVED = 'approved';

    public const string NONE = 'none';

    public function scopeFor(User $user): string
    {
        if ($user->roleAssignments->contains(fn ($r) => $r->role === Role::SdaoMember)) {
            return self::ALL;
        }

        if ($this->organizationIds($user) !== []) {
            return self::ORGANIZATION;
        }

        return $user->roleAssignments->contains(fn ($r) => $r->role !== Role::Student)
            ? self::APPROVED
            : self::NONE;
    }

    /**
     * Narrows a query on `documents` to the ones this user may see on the calendar.
     *
     * @param  Builder<Document>  $documents
     * @return Builder<Document>
     */
    public function restrict(Builder $documents, User $user): Builder
    {
        return match ($this->scopeFor($user)) {
            self::ALL => $documents,
            self::ORGANIZATION => $documents->whereIn('organization_id', $this->organizationIds($user)),
            self::APPROVED => $documents->whereIn('id', DocumentTransition::query()
                ->select('document_id')
                ->where('actor_id', $user->id)
                ->whereIn('action', [TransitionAction::Approved->value, TransitionAction::Completed->value])),
            default => $documents->whereRaw('1 = 0'),
        };
    }

    /** @return array<int, int> */
    private function organizationIds(User $user): array
    {
        return $user->organizationMemberships()->active()->pluck('organization_id')->all();
    }
}
