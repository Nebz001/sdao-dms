<?php

namespace App\Dashboard;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The "what needs SDAO's attention" half of the admin dashboard: the upcoming
 * unapproved activities alert, the five stat tiles, and the who-are-we-waiting-on
 * split. Everything about in-review documents reads from InReviewSnapshot, and
 * everything about returned documents reads from returnedDocuments(), so the
 * tiles, the table and the split always agree.
 */
class AdminAttentionData
{
    /** How far ahead an unapproved activity counts as "happening soon". */
    public const int UPCOMING_WINDOW_DAYS = 7;

    /** Tile hints list organization names only up to this many. */
    private const int NAMED_ORGS_LIMIT = 3;

    /** @var Collection<int, Document>|null */
    private ?Collection $returned = null;

    public function __construct(private readonly InReviewSnapshot $snapshot) {}

    /**
     * Activities within the next 7 days that are not yet approved: a row on an
     * Approved calendar with no Approved proposal against it, or an off-calendar
     * proposal still in Draft, In Review or Returned. A Rejected proposal is
     * left out (the org must file a new one). Soonest first.
     *
     * @return Collection<int, array{id: int, name: string, organizationName: string, date: string}>
     */
    public function upcomingUnapprovedActivities(): Collection
    {
        $activities = CalendarActivity::query()
            ->whereBetween('activity_date', [today(), today()->addDays(self::UPCOMING_WINDOW_DAYS)])
            ->with('calendar.document.organization')
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->get();

        $approvedProposalActivityIds = ActivityProposal::query()
            ->whereIn('calendar_activity_id', $activities->pluck('id'))
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::Approved->value))
            ->pluck('calendar_activity_id');

        return $activities
            ->filter(function (CalendarActivity $activity) use ($approvedProposalActivityIds) {
                $document = $activity->calendar->document;

                if ($document->form_type === FormType::ActivityProposal) {
                    return in_array($document->status, [DocumentStatus::Draft, DocumentStatus::InReview, DocumentStatus::Returned], true);
                }

                return $document->status === DocumentStatus::Approved
                    && ! $approvedProposalActivityIds->contains($activity->id);
            })
            ->map(fn (CalendarActivity $activity) => [
                'id' => $activity->id,
                'name' => $activity->name,
                'organizationName' => $activity->calendar->document->organization->name,
                'date' => $activity->activity_date->toDateString(),
            ])
            ->values();
    }

    /**
     * Returned documents: sent back to the student and not resubmitted yet.
     *
     * @return Collection<int, Document>
     */
    public function returnedDocuments(): Collection
    {
        return $this->returned ??= Document::query()
            ->with([...DocumentDisplayTitle::relations(), 'latestTransition'])
            ->where('status', DocumentStatus::Returned->value)
            ->get();
    }

    /**
     * @return array<int, array{key: string, label: string, count: int, href: string, hint: string, hintTone: string}>
     */
    public function tiles(): array
    {
        $rows = $this->snapshot->rows();
        $sdaoRows = $rows->filter->isSdaoStep();
        $overFollowUp = $sdaoRows->where('idleDays', '>', InReviewSnapshot::SDAO_FOLLOW_UP_DAYS)->count();
        $oldest = (int) $rows->max('idleDays');
        $returnedOrganizations = $this->returnedDocuments()->pluck('organization_id')->unique()->count();
        $pendingChanges = OfficerChangeRequest::query()->pending()->count();
        $noAdviser = $this->approvedOrganizationsWithoutAdviser();

        return [
            [
                'key' => 'awaiting_sdao',
                'label' => 'Awaiting SDAO review',
                'count' => $sdaoRows->count(),
                'href' => route('admin.stuck-documents.index', ['role' => Role::SdaoMember->value]),
                'hint' => $overFollowUp > 0
                    ? "{$overFollowUp} waiting over ".InReviewSnapshot::SDAO_FOLLOW_UP_DAYS.' days'
                    : 'None waiting over '.InReviewSnapshot::SDAO_FOLLOW_UP_DAYS.' days',
                'hintTone' => $overFollowUp > 0 ? 'warning' : 'muted',
            ],
            [
                'key' => 'stuck_with_approvers',
                'label' => 'Stuck with approvers',
                'count' => $rows->count(),
                'href' => route('admin.stuck-documents.index', ['waiting_on' => 'approver']),
                'hint' => $rows->isEmpty() ? 'Nothing is waiting' : 'Oldest idle '.$oldest.' '.Str::plural('day', $oldest),
                'hintTone' => match (InReviewSnapshot::tierFor($oldest)) {
                    'stale' => 'destructive',
                    'aging' => 'warning',
                    default => 'muted',
                },
            ],
            [
                'key' => 'returned',
                'label' => 'Returned, not resubmitted',
                'count' => $this->returnedDocuments()->count(),
                'href' => route('admin.stuck-documents.index', ['waiting_on' => 'org']),
                'hint' => 'Waiting on '.$returnedOrganizations.' '.Str::plural('organization', $returnedOrganizations),
                'hintTone' => 'muted',
            ],
            [
                'key' => 'pending_accounts',
                'label' => 'Pending accounts',
                'count' => User::query()->where('account_status', AccountStatus::Unverified->value)->count(),
                'href' => route('admin.pending-accounts.index'),
                'hint' => 'Plus '.$pendingChanges.' officer change '.Str::plural('request', $pendingChanges),
                'hintTone' => 'muted',
            ],
            [
                'key' => 'without_adviser',
                'label' => 'Organizations without adviser',
                'count' => $noAdviser->count(),
                'href' => route('admin.organizations.index', ['adviser' => 'none']),
                'hint' => $this->noAdviserHint($noAdviser),
                'hintTone' => 'muted',
            ],
        ];
    }

    /**
     * The two separate follow-up buckets: documents sitting with an approver
     * (SDAO chases the approver) and documents returned to the org (SDAO
     * chases the officers). Never mixed.
     *
     * @return array{total: int, approver: array{count: int, href: string}, org: array{count: int, href: string}}
     */
    public function waitingSplit(): array
    {
        $approver = $this->snapshot->rows()->count();
        $org = $this->returnedDocuments()->count();

        return [
            'total' => $approver + $org,
            'approver' => ['count' => $approver, 'href' => route('admin.stuck-documents.index', ['waiting_on' => 'approver'])],
            'org' => ['count' => $org, 'href' => route('admin.stuck-documents.index', ['waiting_on' => 'org'])],
        ];
    }

    /**
     * The per-approver table: top 5 groups plus how many there are in total.
     *
     * @return array{rows: array<int, array{key: string, name: string, line: string, waiting: int, oldest: int, median: int, tier: string, href: string}>, total: int, viewAllHref: string}
     */
    public function stuckByApprover(int $limit = 5): array
    {
        $groups = $this->snapshot->groups();

        return [
            'rows' => $groups->take($limit)->map(fn (array $group) => [
                ...$group,
                'href' => route('admin.stuck-documents.index', ['approver' => $group['key']]),
            ])->values()->all(),
            'total' => $groups->count(),
            'viewAllHref' => route('admin.stuck-documents.index', ['waiting_on' => 'approver']),
        ];
    }

    /**
     * The longest-idle documents that are waiting on an approver (never
     * returned ones), oldest first.
     *
     * @return array<int, array{id: int, title: string, organizationName: string, approverName: string, idleDays: int, tier: string, href: string}>
     */
    public function oldestInReview(int $limit = 5): array
    {
        return $this->snapshot->rows()->take($limit)->map(fn (StuckDocument $row) => [
            'id' => $row->document->id,
            'title' => DocumentDisplayTitle::for($row->document),
            'organizationName' => $row->document->organization->name,
            'approverName' => $row->approverName,
            'idleDays' => $row->idleDays,
            'tier' => $row->tier,
            'href' => DocumentDisplayTitle::href($row->document),
        ])->values()->all();
    }

    /**
     * Organizations that have actually been approved but have no adviser
     * bound. A pending registration has none by design (the adviser is only
     * bound at approval), so those are not a gap.
     *
     * @return Collection<int, Organization>
     */
    public function approvedOrganizationsWithoutAdviser(): Collection
    {
        $approvedOrgIds = Document::query()
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('status', DocumentStatus::Approved->value)
            ->pluck('organization_id');

        return Organization::query()
            ->whereIn('id', $approvedOrgIds)
            ->whereDoesntHave('adviser')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     */
    private function noAdviserHint(Collection $organizations): string
    {
        if ($organizations->isEmpty()) {
            return 'Every approved organization has one';
        }

        if ($organizations->count() <= self::NAMED_ORGS_LIMIT) {
            return $organizations->pluck('name')->implode(', ');
        }

        $available = User::query()
            ->active()
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::Adviser->value)->whereNull('organization_id'))
            ->count();

        return $available.' '.Str::plural('adviser', $available).' available to assign';
    }
}
