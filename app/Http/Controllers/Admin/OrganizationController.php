<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Organizations\OrganizationStatusResolver;
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
    public function index(Request $request, OrganizationStatusResolver $statusResolver): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // RegistrationController/DocumentArchiveController.
        $status = OrganizationStatus::tryFrom($request->string('status')->toString())?->value;
        $search = $request->string('search')->trim()->toString();

        $organizations = Organization::query()
            ->with(['school:id,name', 'program:id,name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();

        $statuses = $statusResolver->forMany($organizations);

        // Reflects the search filter but NOT the status filter, so the stats
        // strip always shows what selecting each status would yield — same
        // trick as RegistrationController/DocumentArchiveController, applied
        // in PHP here since status is derived rather than a DB column.
        $counts = $statuses->countBy(fn ($result) => $result->status->value);

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
                'search' => $search,
            ],
            // Values only — the client labels them with statusLabel() from
            // @/lib/utils, same idiom as RegistrationController::index().
            'statuses' => collect(OrganizationStatus::cases())
                ->map(fn (OrganizationStatus $s) => ['value' => $s->value])
                ->values(),
            'stats' => [
                'total' => $organizations->count(),
                'active' => (int) ($counts[OrganizationStatus::Active->value] ?? 0),
                'needsRenewal' => (int) ($counts[OrganizationStatus::NeedsRenewal->value] ?? 0),
                'pendingReview' => (int) ($counts[OrganizationStatus::PendingReview->value] ?? 0),
                'inactive' => (int) ($counts[OrganizationStatus::Inactive->value] ?? 0),
            ],
        ]);
    }
}
