<?php

namespace App\Organizations;

use App\Calendar\ActivityCalendarEligibilityResult;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationStatus;
use App\Enums\RenewalEligibility;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\OrganizationMembership;
use App\Models\WorkflowStep;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicPeriod;
use App\Support\DisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Read model behind the student organization dashboard — one method per
 * section, mirroring App\Approval\ApproverDashboardData's shape for the
 * approver side. Every query is scoped to $organization, and the class can
 * only be built from an ACTIVE OrganizationMembership (see for()), so
 * cross-organization leakage is structural, not just filtered out after the
 * fact — a student can never see another organization's documents or
 * notifications through this class.
 *
 * Two things are computed once and reused across methods: open() (this
 * org's Draft/InReview/Returned documents) and history() (every
 * Submitted/Advanced/Resubmitted/Returned/Rejected/Completed transition on
 * this org's documents, ever). Both back several sections at no extra query
 * cost.
 */
class StudentDashboardData
{
    private const int NEEDS_ACTION_LIMIT = 10;

    private const int TRACKER_LIMIT = 5;

    private const int UPCOMING_LIMIT = 6;

    private const int REPORTS_DUE_LIMIT = 4;

    /** How many days out "Upcoming Activities" looks — deliberately its own constant, not ApproverQueue::EVENT_SOON_DAYS (a different audience, a different window). */
    private const int UPCOMING_WINDOW_DAYS = 30;

    private const int SUBMISSION_MONTHS = 8;

    /** The three transition actions that "activate" a step — the moment a document reached its current step. Mirrors ApproverDashboardData::ACTIVATION_ACTIONS. */
    private const array ACTIVATION_ACTIONS = [
        TransitionAction::Submitted,
        TransitionAction::Advanced,
        TransitionAction::Resubmitted,
    ];

    /** The three transition actions that end a "review round" — mirrors ApproverDashboardData::DECISION_ACTIONS, plus Completed (final-step quorum, the org-wide equivalent of a single approver's own decision). */
    private const array DECISION_ACTIONS = [
        TransitionAction::Returned,
        TransitionAction::Rejected,
        TransitionAction::Completed,
    ];

    /** @var EloquentCollection<int, Document>|null */
    private ?EloquentCollection $open = null;

    /** @var Collection<int, DocumentTransition>|null */
    private ?Collection $history = null;

    /** @var Collection<int, Collection<int, DocumentTransition>>|null */
    private ?Collection $historyByDocument = null;

    /** @var EloquentCollection<int, Document>|null */
    private ?EloquentCollection $reportable = null;

    private ?OrganizationStatusResult $status = null;

    private function __construct(
        private readonly OrganizationMembership $membership,
        private readonly AcademicPeriod $period,
        private readonly OrganizationStatusResolver $statusResolver,
        private readonly SubmitActivityCalendar $calendars,
    ) {}

    public static function for(OrganizationMembership $membership, AcademicPeriod $period): self
    {
        if (! $membership->is_active) {
            throw new InvalidArgumentException('StudentDashboardData requires an active organization membership.');
        }

        $membership->loadMissing('organization');

        return new self(
            $membership,
            $period,
            app(OrganizationStatusResolver::class),
            app(SubmitActivityCalendar::class),
        );
    }

    /**
     * @return array{organizationName: string, organizationStatus: string, officerPosition: string, academicYear: string, termLabel: string, historyHref: string}
     */
    public function meta(): array
    {
        return [
            'organizationName' => $this->membership->organization->name,
            'organizationStatus' => $this->status()->status->value,
            'officerPosition' => $this->membership->position->label(),
            'academicYear' => $this->period->academicYear,
            'termLabel' => $this->period->term->label(),
            'historyHref' => route('document-history.index'),
        ];
    }

    /**
     * @return array{
     *     totalSubmitted: array{count: int, href: string},
     *     inReview: array{count: int, href: string},
     *     needsRevision: array{count: int, href: string},
     *     approved: array{count: int, href: string},
     *     averageTimeToDecision: array{hours: float|null, sampleSize: int, href: string},
     * }
     */
    public function kpis(): array
    {
        [$start, $end] = $this->period->academicYearRange();

        $totalSubmitted = $this->history()
            ->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Submitted
                && $t->created_at->gte($start) && $t->created_at->lt($end))
            ->count();

        $approved = $this->history()
            ->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Completed
                && $t->created_at->gte($start) && $t->created_at->lt($end))
            ->count();

        $inReview = $this->open()->filter(fn (Document $d) => $d->status === DocumentStatus::InReview)->count();
        $needsRevision = $this->open()->filter(fn (Document $d) => $d->status === DocumentStatus::Returned)->count();

        return [
            'totalSubmitted' => [
                'count' => $totalSubmitted,
                'href' => route('document-history.index'),
            ],
            'inReview' => [
                'count' => $inReview,
                'href' => route('document-history.index', ['status' => DocumentStatus::InReview->value]),
            ],
            'needsRevision' => [
                'count' => $needsRevision,
                'href' => route('document-history.index', ['status' => DocumentStatus::Returned->value]),
            ],
            'approved' => [
                'count' => $approved,
                'href' => route('document-history.index', ['status' => DocumentStatus::Approved->value]),
            ],
            'averageTimeToDecision' => [
                ...$this->averageTimeToDecision(),
                'href' => route('document-history.index'),
            ],
        ];
    }

    /**
     * @return array{total: int, items: array<int, array{
     *     id: string, kind: string, title: string, formTypeLabel: string,
     *     comment: string|null, returnedByName: string|null, returnedAt: string|null,
     *     flaggedCount: int, actionLabel: string, actionHref: string,
     * }>}
     */
    public function needsAction(): array
    {
        $returned = $this->open()
            ->filter(fn (Document $d) => $d->status === DocumentStatus::Returned)
            ->map(function (Document $d) {
                $returnTransition = $this->lastReturnedTransition($d);
                $returnedAt = $returnTransition?->created_at ?? $d->updated_at;

                return [
                    'row' => [
                        'id' => "doc-{$d->id}",
                        'kind' => 'returned',
                        'title' => $d->title,
                        'formTypeLabel' => $d->form_type->label(),
                        'comment' => $returnTransition?->comment,
                        'returnedByName' => $returnTransition?->actor?->name,
                        'returnedAt' => $returnedAt->toIso8601String(),
                        'flaggedCount' => count($returnTransition?->flagged_sections ?? []),
                        'actionLabel' => 'Fix and resubmit',
                        'actionHref' => route($d->form_type->studentEditRouteName(), $d),
                    ],
                    'sortKey' => $returnedAt->toIso8601String(),
                ];
            })
            ->sortBy('sortKey')
            ->map(fn (array $entry) => $entry['row']);

        // Only Activity Proposal ever sits in Draft (its two-step submission
        // persists a draft from step 1 — CLAUDE.md) — every other form type
        // is created already-submitted, so this filter is a correctness
        // guard, not just a UI grouping: 'activity-proposals.continue' below
        // would 404 on any other form type.
        $drafts = $this->open()
            ->filter(fn (Document $d) => $d->status === DocumentStatus::Draft
                && $d->form_type === FormType::ActivityProposal)
            ->map(fn (Document $d) => [
                'id' => "doc-{$d->id}",
                'kind' => 'draft',
                'title' => $d->title,
                'formTypeLabel' => $d->form_type->label(),
                'comment' => null,
                'returnedByName' => null,
                'returnedAt' => null,
                'flaggedCount' => 0,
                'actionLabel' => 'Continue draft',
                'actionHref' => route('activity-proposals.continue', $d),
            ]);

        $items = $returned->concat($drafts)->values();

        return [
            'total' => $items->count(),
            'items' => $items->take(self::NEEDS_ACTION_LIMIT)->all(),
        ];
    }

    /**
     * @return array{total: int, items: array<int, array{
     *     id: int, title: string, formTypeLabel: string, status: string,
     *     waitingSince: string|null, href: string,
     *     steps: array<int, array{position: int, label: string, state: string, requiredApprovals: int, approvalsSoFar: int}>,
     * }>}
     */
    public function tracker(): array
    {
        $rows = $this->open()
            ->filter(fn (Document $d) => in_array($d->status, [DocumentStatus::InReview, DocumentStatus::Returned], true))
            ->map(function (Document $d) {
                $waitingSince = $this->stepWaitingSince($d);

                return [
                    'row' => [
                        'id' => $d->id,
                        'title' => $d->title,
                        'formTypeLabel' => $d->form_type->label(),
                        'status' => $d->status->value,
                        'waitingSince' => $waitingSince?->toIso8601String(),
                        'href' => route($d->form_type->studentShowRouteName(), $d),
                        'steps' => $this->trackerSteps($d),
                    ],
                    'sortKey' => $waitingSince?->toIso8601String() ?? '9999',
                ];
            })
            ->sortBy('sortKey')
            ->map(fn (array $entry) => $entry['row'])
            ->values();

        return [
            'total' => $rows->count(),
            'items' => $rows->take(self::TRACKER_LIMIT)->all(),
        ];
    }

    /**
     * @return array{done: int, applicable: int, items: array<int, array{
     *     key: string, label: string, tier: 'required'|'conditional'|'info', state: 'done'|'in_progress'|'action_needed'|'not_applicable'|'info', detail: string, href: string|null,
     * }>}
     */
    public function requirements(): array
    {
        $items = [
            $this->coverageRequirement(),
            $this->renewalRequirement(),
            $this->calendarRequirement(),
            $this->proposalsRequirement(),
            $this->reportsRequirement(),
        ];

        $applicable = collect($items)->filter(fn (array $i) => $i['state'] !== 'not_applicable' && $i['tier'] !== 'info');
        $done = $applicable->filter(fn (array $i) => $i['state'] === 'done');

        return [
            'done' => $done->count(),
            'applicable' => $applicable->count(),
            'items' => $items,
        ];
    }

    /**
     * The short status chips on the Submit hub's option cards. Each one is
     * read from data the dashboard already computes; an option with nothing
     * real to say simply has no entry, so a chip is never invented.
     *
     * @return array<string, array{label: string, tone: 'neutral'|'warning'}>
     */
    public function hubChips(): array
    {
        $chips = [];
        $today = DisplayTimezone::convert(now())->toDateString();
        $termLabel = $this->period->term->label();

        $reportsDue = $this->reportable()
            ->filter(fn (Document $d) => $d->activityProposal?->calendarActivity !== null
                && $d->activityProposal->calendarActivity->activity_date->toDateString() < $today)
            ->count();

        if ($reportsDue > 0) {
            $chips['report'] = [
                'label' => $reportsDue === 1 ? '1 activity needs a report' : "{$reportsDue} activities need a report",
                'tone' => 'warning',
            ];
        }

        if ($this->status()->coversThroughAcademicYear !== null) {
            $chips['registration'] = [
                'label' => "Covered through {$this->status()->coversThroughAcademicYear}",
                'tone' => 'neutral',
            ];
        }

        $nextYear = $this->period->nextAcademicYear();

        $chips += match ($this->status()->eligibility->status) {
            RenewalEligibility::Eligible => ['renewal' => ['label' => 'Renewal is open', 'tone' => 'warning']],
            RenewalEligibility::AlreadyFiledThisYear => ['renewal' => ['label' => "Filed for {$nextYear}", 'tone' => 'neutral']],
            RenewalEligibility::SeasonClosed => ['renewal' => ['label' => 'Opens in 3rd term', 'tone' => 'neutral']],
            RenewalEligibility::NotYetDue, RenewalEligibility::NoPriorRecord => [],
        };

        $chips['activity_calendar'] = $this->calendarEligibility()->existingDocument !== null
            ? ['label' => "Filed for {$termLabel}", 'tone' => 'neutral']
            : ['label' => "Not filed for {$termLabel} yet", 'tone' => 'warning'];

        $drafts = $this->open()
            ->filter(fn (Document $d) => $d->status === DocumentStatus::Draft
                && $d->form_type === FormType::ActivityProposal)
            ->count();

        if ($drafts > 0) {
            $chips['draft'] = [
                'label' => $drafts === 1 ? '1 draft saved' : "{$drafts} drafts saved",
                'tone' => 'neutral',
            ];
        }

        return $chips;
    }

    /**
     * @return array<int, array{formType: string, label: string, href: string, enabled: bool, reason: string|null}>
     */
    public function quickSubmit(): array
    {
        $renewalEligibility = $this->status()->eligibility;
        $calendarEligibility = $this->calendarEligibility();

        return [
            [
                'formType' => FormType::OrganizationRegistration->value,
                'label' => 'Submit Registration',
                'href' => route('registrations.create'),
                'enabled' => false,
                'reason' => 'Already registered',
            ],
            [
                'formType' => FormType::OrganizationRenewal->value,
                'label' => 'Submit Renewal',
                'href' => route('renewals.create'),
                'enabled' => $renewalEligibility->isEligible(),
                'reason' => $renewalEligibility->isEligible() ? null : $this->renewalReason($renewalEligibility->status),
            ],
            [
                'formType' => FormType::ActivityCalendar->value,
                'label' => 'Submit Activity Calendar',
                'href' => route('activity-calendars.create'),
                'enabled' => $calendarEligibility->isEligible(),
                'reason' => $calendarEligibility->isEligible() ? null : sprintf('Already filed for %s', $this->period->term->label()),
            ],
            [
                'formType' => FormType::ActivityProposal->value,
                'label' => 'Submit Activity Proposal',
                'href' => route('activity-proposals.create'),
                'enabled' => true,
                'reason' => null,
            ],
            [
                'formType' => FormType::AfterActivityReport->value,
                'label' => 'Submit Report',
                'href' => route('reports.create'),
                'enabled' => $this->reportable()->isNotEmpty(),
                'reason' => $this->reportable()->isNotEmpty() ? null : 'No approved activities awaiting a report',
            ],
        ];
    }

    /**
     * @return array{
     *     upcoming: array<int, array{id: int, title: string, venue: string, date: string, startTime: string, endTime: string, status: string, href: string}>,
     *     reportsDue: array<int, array{id: int, title: string, date: string, daysSince: int, fileHref: string}>,
     * }
     */
    public function upcomingActivities(): array
    {
        $today = DisplayTimezone::convert(now())->toDateString();
        $windowEnd = DisplayTimezone::convert(now())->addDays(self::UPCOMING_WINDOW_DAYS)->toDateString();

        $upcoming = Document::query()
            ->where('organization_id', $this->membership->organization_id)
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('status', DocumentStatus::Approved->value)
            ->with('activityProposal.calendarActivity')
            ->get()
            ->filter(fn (Document $d) => $d->activityProposal?->calendarActivity !== null)
            ->filter(function (Document $d) use ($today, $windowEnd) {
                $date = $d->activityProposal->calendarActivity->activity_date->toDateString();

                return $date >= $today && $date <= $windowEnd;
            })
            ->sortBy(fn (Document $d) => $d->activityProposal->calendarActivity->activity_date->toDateString().' '.$d->activityProposal->calendarActivity->start_time)
            ->take(self::UPCOMING_LIMIT)
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->activityProposal->title,
                'venue' => $d->activityProposal->calendarActivity->venue,
                'date' => $d->activityProposal->calendarActivity->activity_date->toDateString(),
                'startTime' => $d->activityProposal->calendarActivity->start_time,
                'endTime' => $d->activityProposal->calendarActivity->end_time,
                'status' => $d->status->value,
                'href' => route($d->form_type->studentShowRouteName(), $d),
            ])
            ->values()
            ->all();

        $reportsDue = $this->reportable()
            ->filter(fn (Document $d) => $d->activityProposal?->calendarActivity !== null
                && $d->activityProposal->calendarActivity->activity_date->toDateString() < $today)
            ->sortBy(fn (Document $d) => $d->activityProposal->calendarActivity->activity_date->toDateString())
            ->take(self::REPORTS_DUE_LIMIT)
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->activityProposal->title,
                'date' => $d->activityProposal->calendarActivity->activity_date->toDateString(),
                'daysSince' => (int) $d->activityProposal->calendarActivity->activity_date->diffInDays(now()),
                'fileHref' => route('reports.create'),
            ])
            ->values()
            ->all();

        return ['upcoming' => $upcoming, 'reportsDue' => $reportsDue];
    }

    /**
     * @return array<int, array{monthStart: string, label: string, count: int}>
     */
    public function submissionsOverTime(): array
    {
        $since = DisplayTimezone::convert(now())->startOfMonth()->subMonths(self::SUBMISSION_MONTHS - 1);

        $tally = [];

        foreach ($this->history() as $transition) {
            if ($transition->action !== TransitionAction::Submitted) {
                continue;
            }

            $monthIndex = (int) $since->diffInMonths(DisplayTimezone::convert($transition->created_at->copy())->startOfMonth());

            if ($monthIndex < 0 || $monthIndex >= self::SUBMISSION_MONTHS) {
                continue;
            }

            $tally[$monthIndex] = ($tally[$monthIndex] ?? 0) + 1;
        }

        return collect(range(0, self::SUBMISSION_MONTHS - 1))
            ->map(function (int $i) use ($since, $tally) {
                $monthStart = $since->copy()->addMonths($i);

                return [
                    'monthStart' => $monthStart->toDateString(),
                    'label' => $monthStart->format('M Y'),
                    'count' => $tally[$i] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, tier: 'required', state: 'done'|'in_progress'|'action_needed', detail: string, href: string|null}
     */
    private function coverageRequirement(): array
    {
        $requirements = $this->status()->requirements;

        $state = match (true) {
            $requirements->coverageCurrent => 'done',
            $this->status()->status === OrganizationStatus::PendingReview => 'in_progress',
            default => 'action_needed',
        };

        $detail = match ($state) {
            'done' => $this->status()->coversThroughAcademicYear !== null
                ? "Covered through {$this->status()->coversThroughAcademicYear}"
                : 'Covered for this academic year',
            'in_progress' => 'Registration or renewal under review',
            default => "Not covered for {$this->period->academicYear}",
        };

        return [
            'key' => 'coverage',
            'label' => 'Registration & coverage',
            'tier' => 'required',
            'state' => $state,
            'detail' => $detail,
            'href' => route('organizations.mine'),
        ];
    }

    /**
     * @return array{key: string, label: string, tier: 'required'|'conditional', state: 'done'|'in_progress'|'action_needed'|'not_applicable', detail: string, href: string|null}
     */
    private function renewalRequirement(): array
    {
        $eligibility = $this->status()->eligibility;
        $nextYear = $this->period->nextAcademicYear();

        if ($eligibility->status === RenewalEligibility::SeasonClosed || $eligibility->status === RenewalEligibility::NoPriorRecord) {
            return [
                'key' => 'renewal',
                'label' => "Renewal for {$nextYear}",
                'tier' => 'conditional',
                'state' => 'not_applicable',
                'detail' => 'Opens in 3rd term',
                'href' => null,
            ];
        }

        if ($eligibility->status === RenewalEligibility::NotYetDue) {
            return [
                'key' => 'renewal',
                'label' => "Renewal for {$nextYear}",
                'tier' => 'conditional',
                'state' => 'done',
                'detail' => 'Already covered through this renewal season',
                'href' => null,
            ];
        }

        if ($eligibility->status === RenewalEligibility::Eligible) {
            return [
                'key' => 'renewal',
                'label' => "Renewal for {$nextYear}",
                'tier' => 'conditional',
                'state' => 'action_needed',
                'detail' => 'Due this term',
                'href' => route('renewals.create'),
            ];
        }

        // AlreadyFiledThisYear — look up the filed document's own status.
        $filed = $this->currentYearRenewalDocument();
        $state = match ($filed?->status) {
            DocumentStatus::Approved => 'done',
            DocumentStatus::Returned => 'action_needed',
            default => 'in_progress',
        };

        return [
            'key' => 'renewal',
            'label' => "Renewal for {$nextYear}",
            'tier' => 'conditional',
            'state' => $state,
            'detail' => match ($state) {
                'done' => 'Filed and approved',
                'action_needed' => 'Returned for revision',
                default => 'Filed — under review',
            },
            'href' => $filed !== null ? route('renewals.show', $filed) : null,
        ];
    }

    /**
     * @return array{key: string, label: string, tier: 'required', state: 'done'|'in_progress'|'action_needed', detail: string, href: string|null}
     */
    private function calendarRequirement(): array
    {
        $eligibility = $this->calendarEligibility();
        $existing = $eligibility->existingDocument;
        $termLabel = $this->period->term->label();

        $state = match ($existing?->status) {
            DocumentStatus::Approved => 'done',
            DocumentStatus::InReview => 'in_progress',
            DocumentStatus::Returned => 'action_needed',
            default => 'action_needed',
        };

        $detail = match ($state) {
            'done' => 'Approved',
            'in_progress' => 'Under review',
            default => $existing !== null ? 'Returned for revision' : 'Not filed yet',
        };

        return [
            'key' => 'activity_calendar',
            'label' => "Activity calendar ({$termLabel})",
            'tier' => 'required',
            'state' => $state,
            'detail' => $detail,
            'href' => $existing !== null
                ? route('activity-calendars.show', $existing)
                : route('activity-calendars.create'),
        ];
    }

    /**
     * @return array{key: string, label: string, tier: 'info', state: 'info', detail: string, href: string|null}
     */
    private function proposalsRequirement(): array
    {
        $approved = Document::query()
            ->where('organization_id', $this->membership->organization_id)
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('status', DocumentStatus::Approved->value)
            ->count();

        $inProgress = $this->open()
            ->filter(fn (Document $d) => $d->form_type === FormType::ActivityProposal
                && in_array($d->status, [DocumentStatus::InReview, DocumentStatus::Returned], true))
            ->count();

        return [
            'key' => 'activity_proposals',
            'label' => 'Activity proposals',
            'tier' => 'info',
            'state' => 'info',
            'detail' => "{$approved} approved · {$inProgress} in progress",
            'href' => route('activity-proposals.create'),
        ];
    }

    /**
     * @return array{key: string, label: string, tier: 'conditional', state: 'done'|'in_progress'|'action_needed'|'not_applicable', detail: string, href: string|null}
     */
    private function reportsRequirement(): array
    {
        $today = DisplayTimezone::convert(now())->toDateString();

        $due = $this->reportable()
            ->filter(fn (Document $d) => $d->activityProposal?->calendarActivity !== null
                && $d->activityProposal->calendarActivity->activity_date->toDateString() < $today)
            ->count();

        if ($due > 0) {
            return [
                'key' => 'reports',
                'label' => 'After-activity reports',
                'tier' => 'conditional',
                'state' => 'action_needed',
                'detail' => $due === 1 ? '1 report due' : "{$due} reports due",
                'href' => route('reports.create'),
            ];
        }

        $openReport = $this->open()->first(fn (Document $d) => $d->form_type === FormType::AfterActivityReport);

        if ($openReport !== null) {
            return [
                'key' => 'reports',
                'label' => 'After-activity reports',
                'tier' => 'conditional',
                'state' => $openReport->status === DocumentStatus::Returned ? 'action_needed' : 'in_progress',
                'detail' => $openReport->status === DocumentStatus::Returned ? 'Returned for revision' : 'Under review',
                'href' => route($openReport->form_type->studentShowRouteName(), $openReport),
            ];
        }

        $pastApproved = Document::query()
            ->where('organization_id', $this->membership->organization_id)
            ->where('form_type', FormType::ActivityProposal->value)
            ->where('status', DocumentStatus::Approved->value)
            ->whereHas('activityProposal.calendarActivity', fn ($q) => $q->where('activity_date', '<', $today))
            ->exists();

        return [
            'key' => 'reports',
            'label' => 'After-activity reports',
            'tier' => 'conditional',
            'state' => $pastApproved ? 'done' : 'not_applicable',
            'detail' => $pastApproved ? 'All filed' : 'No completed activities yet',
            'href' => null,
        ];
    }

    private function renewalReason(RenewalEligibility $status): string
    {
        $nextYear = $this->period->nextAcademicYear();

        return match ($status) {
            RenewalEligibility::SeasonClosed => 'Opens in 3rd term',
            RenewalEligibility::AlreadyFiledThisYear => "Already filed for {$nextYear}",
            RenewalEligibility::NotYetDue => 'Not yet due',
            RenewalEligibility::NoPriorRecord => 'No approved registration to renew',
            RenewalEligibility::Eligible => '',
        };
    }

    /**
     * The step at which the given document currently sits became active —
     * i.e. the last Submitted/Advanced/Resubmitted transition at the
     * document's current_step_position. For a Returned document this is
     * instead the return time itself (the clock the student cares about is
     * "how long have I had this to fix", not the prior step's activation).
     * Null when the document has no transitions at all (a hand-seeded
     * legacy row) — callers must handle that, never assume a transition
     * exists the way ApproverQueue::waitingSinceTransition() safely can for
     * an always-freshly-submitted InReview document.
     *
     * Deliberately NOT App\Approval\ApproverQueue::waitingSince(): that
     * helper resets to a partial SDAO approval's own timestamp once the
     * first of two required approvals lands, which would understate how
     * long this step has actually been active.
     */
    private function stepWaitingSince(Document $d): ?CarbonInterface
    {
        if ($d->status === DocumentStatus::Returned) {
            return $this->lastReturnedTransition($d)?->created_at ?? $d->updated_at;
        }

        return $d->transitions
            ->filter(fn (DocumentTransition $t) => in_array($t->action, self::ACTIVATION_ACTIONS, true)
                && $t->step_position === $d->current_step_position)
            ->last()
            ?->created_at;
    }

    /**
     * The most recent Returned transition on this document, if any. Broken
     * out into its own method with an explicit nullable return type — rather
     * than the ->filter()->last() chain inline at each call site — because
     * Larastan's generic inference otherwise narrows that chain to "never
     * null" and flags the subsequent ?-> as unnecessary. It is NOT
     * unnecessary: DashboardTest's hand-seeded "Returned with no transitions"
     * fixture exercises exactly this null case, so the nullsafe stays.
     */
    private function lastReturnedTransition(Document $d): ?DocumentTransition
    {
        return $d->transitions
            ->filter(fn (DocumentTransition $t) => $t->action === TransitionAction::Returned)
            ->last();
    }

    /**
     * @return array<int, array{position: int, label: string, state: string, requiredApprovals: int, approvalsSoFar: int}>
     */
    private function trackerSteps(Document $d): array
    {
        $steps = $d->workflowTemplate?->steps;

        if ($steps === null || $d->current_step_position === null) {
            return [];
        }

        $currentPosition = $d->current_step_position;

        return $steps->map(function (WorkflowStep $step) use ($d, $currentPosition) {
            $state = match (true) {
                $step->position < $currentPosition => 'done',
                $step->position > $currentPosition => 'upcoming',
                $d->status === DocumentStatus::Returned => 'returned',
                default => 'current',
            };

            $approvalsSoFar = $step->position === $currentPosition
                ? $d->stepApprovals->where('step_position', $step->position)->count()
                : 0;

            return [
                'position' => $step->position,
                'label' => $step->role->label(),
                'state' => $state,
                'requiredApprovals' => $step->required_approvals,
                'approvalsSoFar' => $approvalsSoFar,
            ];
        })->values()->all();
    }

    /**
     * @return array{hours: float|null, sampleSize: int}
     */
    private function averageTimeToDecision(): array
    {
        [$start, $end] = $this->period->academicYearRange();

        $decisions = $this->history()
            ->filter(fn (DocumentTransition $t) => in_array($t->action, self::DECISION_ACTIONS, true)
                && $t->created_at->gte($start) && $t->created_at->lt($end));

        if ($decisions->isEmpty()) {
            return ['hours' => null, 'sampleSize' => 0];
        }

        $byDocument = $this->historyByDocument();

        $minutes = $decisions
            ->map(function (DocumentTransition $decision) use ($byDocument) {
                $activation = ($byDocument->get($decision->document_id) ?? collect())
                    ->filter(fn (DocumentTransition $t) => $t->id < $decision->id
                        && $t->step_position === $decision->step_position
                        && in_array($t->action, self::ACTIVATION_ACTIONS, true))
                    ->last();

                return $activation?->created_at->diffInMinutes($decision->created_at);
            })
            ->filter(fn (?float $m) => $m !== null)
            ->values();

        if ($minutes->isEmpty()) {
            return ['hours' => null, 'sampleSize' => 0];
        }

        return [
            'hours' => round(((float) $minutes->avg()) / 60, 1),
            'sampleSize' => $minutes->count(),
        ];
    }

    /**
     * This org's Draft/InReview/Returned documents, with the chain and
     * transition data every section above needs — one query, reused by
     * kpis(), needsAction(), tracker(), and the checklist.
     *
     * @return EloquentCollection<int, Document>
     */
    private function open(): EloquentCollection
    {
        return $this->open ??= Document::query()
            ->where('organization_id', $this->membership->organization_id)
            ->whereIn('status', [DocumentStatus::Draft->value, DocumentStatus::InReview->value, DocumentStatus::Returned->value])
            ->with(['workflowTemplate.steps', 'transitions.actor:id,name', 'stepApprovals'])
            ->get();
    }

    /**
     * Every Submitted/Advanced/Resubmitted/Returned/Rejected/Completed
     * transition on this org's documents, ever — one query feeding kpis(),
     * submissionsOverTime(), and averageTimeToDecision(). Excludes the
     * partial-quorum Approved action (not needed by any of those) and the
     * system-attributed Withdrawn action.
     *
     * @return Collection<int, DocumentTransition>
     */
    private function history(): Collection
    {
        if ($this->history !== null) {
            return $this->history;
        }

        $actions = collect([...self::ACTIVATION_ACTIONS, ...self::DECISION_ACTIONS])
            ->map(fn (TransitionAction $a) => $a->value)
            ->all();

        return $this->history = DocumentTransition::query()
            ->whereHas('document', fn ($q) => $q->where('organization_id', $this->membership->organization_id))
            ->whereIn('action', $actions)
            ->orderBy('id')
            ->get(['id', 'document_id', 'action', 'step_position', 'created_at']);
    }

    /**
     * @return Collection<int, Collection<int, DocumentTransition>>
     */
    private function historyByDocument(): Collection
    {
        return $this->historyByDocument ??= $this->history()->groupBy('document_id');
    }

    private function status(): OrganizationStatusResult
    {
        return $this->status ??= $this->statusResolver->for($this->membership->organization, $this->period);
    }

    private function calendarEligibility(): ActivityCalendarEligibilityResult
    {
        return $this->calendars->eligibilityFor($this->membership->organization, $this->period);
    }

    /**
     * Approved proposals for this org with no live (non-rejected)
     * after-activity report — the exact predicate the report picker uses
     * (see SubmitAfterActivityReport::reportableProposalsFor()), reused so
     * the checklist, the "Report due" hints, and the Quick Submit tile can
     * never disagree with what the picker itself will show.
     *
     * @return EloquentCollection<int, Document>
     */
    private function reportable(): EloquentCollection
    {
        return $this->reportable ??= SubmitAfterActivityReport::reportableProposalsFor($this->membership->organization)
            ->with('activityProposal.calendarActivity')
            ->get();
    }

    /**
     * The non-rejected renewal document covering next academic year, if
     * any — mirrors SubmitOrganizationRenewal::hasNonRejectedRenewalForExactYear()'s
     * exact predicate (that method only returns a boolean; this needs the
     * row itself to read its status for the checklist).
     */
    private function currentYearRenewalDocument(): ?Document
    {
        return Document::query()
            ->where('organization_id', $this->membership->organization_id)
            ->where('form_type', FormType::OrganizationRenewal->value)
            ->where('status', '!=', DocumentStatus::Rejected->value)
            ->whereHas('registrationDetail', fn ($q) => $q->where('covers_academic_year', $this->period->nextAcademicYear()))
            ->first();
    }
}
