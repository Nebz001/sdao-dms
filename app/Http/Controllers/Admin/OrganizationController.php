<?php

namespace App\Http\Controllers\Admin;

use App\Approval\ReviewQueueData;
use App\Dashboard\AdminAttentionData;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationStatus;
use App\Enums\Term;
use App\Enums\TransitionAction;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Organization;
use App\Organizations\OrganizationDetailData;
use App\Organizations\OrganizationStatusResolver;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Every organization with its App\Organizations\OrganizationStatusResolver
     * -derived status and requirements checklist. Status is computed, not a
     * DB column, so the status filter and pagination happen in PHP after
     * resolving — safe at NU Lipa's scale (single digits to dozens of
     * organizations), the same tradeoff OrganizationStatusResolver::forMany()
     * itself already makes for the renewal-eligibility half of the
     * computation. The name search filter still runs at the SQL level, both
     * to narrow what gets resolved and to match the rest of the app's
     * filter conventions.
     */
    public function index(Request $request, OrganizationStatusResolver $statusResolver, AdminAttentionData $attention): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // RegistrationController/DocumentArchiveController.
        $status = OrganizationStatus::tryFrom($request->string('status')->toString())?->value;
        $search = $request->string('search')->trim()->toString();
        // `adviser=none` is the dashboard tile's destination: approved
        // organizations with no adviser bound. Any other value is ignored.
        $withoutAdviser = $request->string('adviser')->toString() === 'none';

        // The stat cards describe every organization, so status is resolved
        // for all of them once; search / adviser narrow only the rows.
        $all = Organization::query()
            ->with(['school:id,name', 'program:id,name'])
            ->orderBy('name')
            ->get();

        $statuses = $statusResolver->forMany($all);

        $matchingIds = Organization::query()
            ->when($withoutAdviser, fn ($query) => $query->whereIn(
                'id',
                $attention->approvedOrganizationsWithoutAdviser()->pluck('id'),
            ))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->pluck('id');

        $organizations = $all->whereIn('id', $matchingIds)->values();

        $filtered = $organizations
            ->when($status, fn (Collection $orgs) => $orgs->filter(
                fn (Organization $org) => $statuses->get($org->id)?->status->value === $status
            ))
            ->values();

        $page = $request->integer('page', 1);
        $items = $filtered->forPage($page, self::PER_PAGE)->values();
        $paginator = new LengthAwarePaginator($items, $filtered->count(), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('admin/organizations/index', [
            'organizations' => [
                'data' => $items->map(function (Organization $org) use ($statuses) {
                    $result = $statuses->get($org->id);
                    $requirements = $result->requirements->toArray();

                    return [
                        'id' => $org->id,
                        'name' => $org->name,
                        'school' => $org->school?->name,
                        'program' => $org->program?->name,
                        'status' => $result->status->value,
                        'renewalDue' => $result->renewalDue,
                        'requirementsMet' => collect($requirements)->where('met', true)->count(),
                        'requirementsTotal' => count($requirements),
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => [
                'status' => $status,
                'adviser' => $withoutAdviser ? 'none' : null,
                'search' => $search,
            ],
            // Values only — the client labels them with statusLabel() from
            // @/lib/utils, same idiom as RegistrationController::index().
            'statuses' => collect(OrganizationStatus::cases())
                ->map(fn (OrganizationStatus $s) => ['value' => $s->value])
                ->values(),
            // Deferred: the page shows skeleton cards until this lands. Always
            // covers every organization, regardless of the filters above.
            'stats' => Inertia::defer(fn () => $this->stats($all, $statuses), 'org-stats'),
        ]);
    }

    /**
     * One organization's standing, requirements, documents and officers. The
     * name renders immediately; every section is deferred so each shows its
     * own skeleton. Same `can:access-admin` gate as the list (route group).
     */
    public function show(Organization $organization, OrganizationDetailData $detail): Response
    {
        return Inertia::render('admin/organizations/show', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'summary' => Inertia::defer(fn () => $detail->summary($organization), 'org-summary'),
            'requirements' => Inertia::defer(fn () => $detail->requirements($organization), 'org-requirements'),
            'documents' => Inertia::defer(fn () => $detail->documents($organization), 'org-documents'),
            'officers' => Inertia::defer(fn () => $detail->officers($organization), 'org-officers'),
        ]);
    }

    /**
     * @param  Collection<int, Organization>  $all
     * @param  Collection<int, OrganizationStatusResult>  $statuses  keyed by organization id
     * @return array<string, mixed>
     */
    private function stats(Collection $all, Collection $statuses): array
    {
        $counts = $statuses->countBy(fn ($result) => $result->status->value);

        $progress = $all->map(function (Organization $org) use ($statuses) {
            $requirements = $statuses->get($org->id)->requirements->toArray();

            return [
                'name' => $org->name,
                'met' => collect($requirements)->where('met', true)->count(),
                'total' => count($requirements),
            ];
        });

        $incomplete = $progress->reject(fn (array $p) => $p['total'] > 0 && $p['met'] >= $p['total']);

        // Ties go to the first by name, since $all is already name-ordered.
        $furthestBehind = $incomplete->sortBy(fn (array $p) => $p['total'] > 0 ? $p['met'] / $p['total'] : 0)->first();

        return [
            'total' => $all->count(),
            'active' => (int) ($counts[OrganizationStatus::Active->value] ?? 0),
            'needsRenewal' => (int) ($counts[OrganizationStatus::NeedsRenewal->value] ?? 0),
            'pendingReview' => (int) ($counts[OrganizationStatus::PendingReview->value] ?? 0),
            'inactive' => (int) ($counts[OrganizationStatus::Inactive->value] ?? 0),
            'missingRequirements' => $incomplete->count(),
            'furthestBehind' => $furthestBehind,
            'oldestPending' => $this->oldestPending($statuses),
            'renewalDue' => $statuses->filter(fn ($result) => $result->renewalDue)->count(),
            'renewalWindow' => $this->renewalWindow(),
        ];
    }

    /**
     * The longest-waiting registration or renewal that is actually sitting in
     * SDAO's queue (InReview, not Returned or Draft), among organizations
     * resolving PendingReview, in the shared review-queue row shape. "Waiting"
     * runs from the latest submitted/resubmitted transition, as in
     * ReviewQueueData.
     *
     * @param  Collection<int, OrganizationStatusResult>  $statuses
     * @return array<string, mixed>|null
     */
    private function oldestPending(Collection $statuses): ?array
    {
        $pendingIds = $statuses->filter(fn ($r) => $r->status === OrganizationStatus::PendingReview)->keys();

        $oldest = Document::query()
            ->with(['organization.school:id,name', 'transitions'])
            ->whereIn('organization_id', $pendingIds)
            ->whereIn('form_type', [FormType::OrganizationRegistration->value, FormType::OrganizationRenewal->value])
            ->where('status', DocumentStatus::InReview->value)
            ->get()
            ->map(fn (Document $d) => [
                'document' => $d,
                'since' => $d->transitions
                    ->whereIn('action', [TransitionAction::Submitted, TransitionAction::Resubmitted])
                    ->last()?->created_at ?? $d->created_at,
            ])
            ->sortBy(fn (array $e) => $e['since']->getTimestamp())
            ->first();

        if ($oldest === null) {
            return null;
        }

        /** @var Document $document */
        $document = $oldest['document'];
        $days = (int) $oldest['since']->diffInDays(now(), true);
        $isRenewal = $document->form_type === FormType::OrganizationRenewal;

        return [
            'id' => $document->id,
            'title' => $document->title,
            'organization' => ['id' => $document->organization->id, 'name' => $document->organization->name],
            'submitted_at' => $oldest['since']->toIso8601String(),
            'waiting_days' => $days,
            'tier' => ReviewQueueData::tierFor($days),
            'extra' => $document->organization->school?->name ?? 'None',
            'noun' => $isRenewal ? 'renewal' : 'registration',
            'href' => route($isRenewal ? 'review.renewals.show' : 'review.registrations.show', $document->id),
        ];
    }

    /**
     * Renewal season is 3rd term (AcademicPeriod::isRenewalSeason()); the
     * months come from the term calendar in AcademicPeriod (still marked
     * PROVISIONAL there). There are no per-organization due dates.
     *
     * @return array{open: bool, closes: string|null, nextOpens: string}
     */
    private function renewalWindow(): array
    {
        $period = CurrentPeriod::get();
        $open = $period->isRenewalSeason();

        // The next 3rd term: this academic year's if it has not started, else the following year's.
        $nextYear = $open ? $period->nextAcademicYear() : $period->academicYear;

        return [
            'open' => $open,
            'closes' => $open ? $period->termRange()[1]->subDay()->format('F Y') : null,
            'nextOpens' => (new AcademicPeriod($nextYear, Term::ThirdTerm))->termRange()[0]->format('F Y'),
        ];
    }
}
