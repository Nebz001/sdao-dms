<?php

namespace App\Support;

use App\Dashboard\AdminAttentionData;
use App\Dashboard\InReviewSnapshot;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The count badges in the sidebar, derived from the same data the pages they
 * point at show, so a badge and its page can never disagree:
 *
 * - review: in-review documents the user can act on right now (the `review`
 *   ability, exactly what each review queue filters by), per form type.
 * - stuck: everything on the Stuck Documents page (in review plus returned).
 * - accounts: the Pending Accounts queue and pending officer change requests.
 * - documents: the viewer's own organization's documents per form type, with
 *   the same scope as DocumentHistoryController (active memberships, plus
 *   documents the viewer submitted themselves).
 *
 * Shared as a closure prop (see HandleInertiaRequests), so it only runs on
 * requests that ask for it.
 */
class NavCounts
{
    public function __construct(
        private readonly InReviewSnapshot $snapshot,
        private readonly AdminAttentionData $attention,
    ) {}

    /**
     * @return array{
     *     stuck: int,
     *     review: array{registrations: int, renewals: int, calendars: int, reports: int, proposals: int},
     *     accounts: array{pending: int, officerChanges: int},
     *     documents: array{registrations: int, renewals: int, calendars: int, proposals: int, reports: int, history: int},
     * }
     */
    public function for(User $user): array
    {
        $user->loadMissing('roleAssignments');

        $isSdao = $user->roleAssignments->contains(fn (RoleAssignment $ra) => $ra->role === Role::SdaoMember);
        $isStaff = $user->roleAssignments->isNotEmpty();

        $reviewable = $isStaff ? $this->reviewableByForm($user) : [];

        return [
            'stuck' => $isSdao ? $this->snapshot->rows()->count() + $this->attention->returnedDocuments()->count() : 0,
            'review' => [
                'registrations' => $reviewable[FormType::OrganizationRegistration->value] ?? 0,
                'renewals' => $reviewable[FormType::OrganizationRenewal->value] ?? 0,
                'calendars' => $reviewable[FormType::ActivityCalendar->value] ?? 0,
                'reports' => $reviewable[FormType::AfterActivityReport->value] ?? 0,
                'proposals' => $reviewable[FormType::ActivityProposal->value] ?? 0,
            ],
            'accounts' => [
                'pending' => $isSdao ? User::query()->where('account_status', AccountStatus::Unverified->value)->count() : 0,
                'officerChanges' => $isSdao ? OfficerChangeRequest::query()->pending()->count() : 0,
            ],
            'documents' => $this->ownDocuments($user),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function reviewableByForm(User $user): array
    {
        return Document::query()
            ->with('workflowTemplate.steps')
            ->where('status', DocumentStatus::InReview->value)
            ->whereNotNull('submitted_by')
            ->get()
            ->filter(fn (Document $d) => Gate::forUser($user)->allows('review', $d))
            ->countBy(fn (Document $d) => $d->form_type->value)
            ->all();
    }

    /**
     * @return array{registrations: int, renewals: int, calendars: int, proposals: int, reports: int, history: int}
     */
    private function ownDocuments(User $user): array
    {
        $organizationIds = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $byForm = Document::query()
            ->where(fn ($q) => $q->whereIn('organization_id', $organizationIds)->orWhere('submitted_by', $user->id))
            ->selectRaw('form_type, count(*) as aggregate')
            ->groupBy('form_type')
            ->pluck('aggregate', 'form_type');

        $count = fn (FormType $type): int => (int) ($byForm[$type->value] ?? 0);

        return [
            'registrations' => $count(FormType::OrganizationRegistration),
            'renewals' => $count(FormType::OrganizationRenewal),
            'calendars' => $count(FormType::ActivityCalendar),
            'proposals' => $count(FormType::ActivityProposal),
            'reports' => $count(FormType::AfterActivityReport),
            'history' => (int) $byForm->sum(),
        ];
    }
}
