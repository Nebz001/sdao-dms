<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Http\Controllers\Controller;
use App\Models\DocumentTransition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The full, filterable destination behind the dashboard's "View all activity"
 * link (AdminDashboardController::recentActivity() is a tight, capped
 * teaser — this is where genuine browsing of document_transitions happens).
 * Structured identically to DocumentArchiveController: same filter-bar shape,
 * same pagination size, same Skeleton/Empty frontend states — reused, not
 * reinvented.
 */
class ActivityLogController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Maps a document's form type to its approver-facing "show" route — see
     * AdminDashboardController::REVIEW_SHOW_ROUTE_NAMES for why this is
     * always reachable regardless of the document's current status.
     *
     * @var array<string, string>
     */
    private const array REVIEW_SHOW_ROUTE_NAMES = [
        'organization_registration' => 'review.registrations.show',
        'organization_renewal' => 'review.renewals.show',
        'activity_calendar' => 'review.activity-calendars.show',
        'activity_proposal' => 'review.activity-proposals.show',
        'after_activity_report' => 'review.reports.show',
    ];

    public function index(Request $request): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // DocumentArchiveController::index().
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $action = TransitionAction::tryFrom($request->string('action')->toString())?->value;
        $search = $request->string('search')->trim()->toString();

        $base = DocumentTransition::query()
            ->when($formType, fn ($query, $value) => $query->whereHas(
                'document',
                fn ($q) => $q->where('form_type', $value)
            ))
            ->when($action, fn ($query, $value) => $query->where('action', $value))
            ->when($search !== '', fn ($query) => $query->whereHas(
                'document',
                fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
            ));

        $total = (clone $base)->count();

        $transitions = (clone $base)
            ->with([
                'actor:id,name',
                'document:id,title,form_type,organization_id',
                'document.organization:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/activity/index', [
            'transitions' => [
                'data' => collect($transitions->items())->map(fn (DocumentTransition $t) => [
                    'id' => $t->id,
                    'actorName' => $t->actor?->name ?? 'System',
                    'action' => $t->action->value,
                    'documentTitle' => $t->document->title,
                    'formTypeLabel' => $t->document->form_type->label(),
                    'organizationName' => $t->document->organization->name,
                    'createdAt' => $t->created_at,
                    'href' => route(self::REVIEW_SHOW_ROUTE_NAMES[$t->document->form_type->value], $t->document),
                ])->values(),
                'meta' => [
                    'current_page' => $transitions->currentPage(),
                    'last_page' => $transitions->lastPage(),
                    'from' => $transitions->firstItem(),
                    'to' => $transitions->lastItem(),
                    'total' => $transitions->total(),
                ],
                'links' => [
                    'prev' => $transitions->previousPageUrl(),
                    'next' => $transitions->nextPageUrl(),
                ],
            ],
            'filters' => [
                'form_type' => $formType,
                'action' => $action,
                'search' => $search,
            ],
            'formTypes' => collect(FormType::cases())
                ->map(fn (FormType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'actions' => collect(TransitionAction::cases())
                ->map(fn (TransitionAction $a) => ['value' => $a->value])
                ->values(),
            'stats' => [
                'total' => $total,
            ],
        ]);
    }
}
