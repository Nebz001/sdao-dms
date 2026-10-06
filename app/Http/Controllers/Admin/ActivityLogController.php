<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\ActivityLogStats;
use App\Dashboard\DocumentDisplayTitle;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Http\Controllers\Controller;
use App\Models\DocumentTransition;
use App\Models\School;
use App\Support\CurrentPeriod;
use App\Support\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
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

    /** The Date filter's default: the current term. */
    private const string DEFAULT_DATE = 'term';

    /** @var array<int, string> */
    private const array DATE_OPTIONS = ['term', 'week', 'last_30', 'academic_year', 'all'];

    /**
     * The date as given when it is a real YYYY-MM-DD calendar date, else null.
     */
    private function validDate(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return null;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
    }

    /**
     * [from, to) for a Date filter option, in the app's storage timezone, or
     * null for "all time". "This week" is the Monday-start week in Asia/Manila.
     *
     * @return array{0: Carbon, 1: Carbon|null}|null
     */
    private function dateWindow(string $date): ?array
    {
        $period = CurrentPeriod::get();
        $now = Date::now();

        return match ($date) {
            'term' => $period->termRange(),
            'academic_year' => $period->academicYearRange(),
            'week' => [DisplayTimezone::convert($now->copy())->startOfWeek()->utc(), null],
            'last_30' => [$now->copy()->subDays(30), null],
            default => null,
        };
    }

    public function index(Request $request, ActivityLogStats $stats): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // DocumentArchiveController::index().
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $action = TransitionAction::tryFrom($request->string('action')->toString())?->value;
        $search = $request->string('search')->trim()->toString();
        // `from` / `to` (YYYY-MM-DD, both days included) are the dashboard
        // weekly chart's destination. An invalid date is treated as no filter.
        $from = $this->validDate($request->string('from')->toString());
        $to = $this->validDate($request->string('to')->toString());
        $customRange = $from !== null || $to !== null;

        // An explicit from/to range wins over the Date filter.
        $date = in_array($request->string('date')->toString(), self::DATE_OPTIONS, true)
            ? $request->string('date')->toString()
            : self::DEFAULT_DATE;
        $window = $customRange ? null : $this->dateWindow($date);

        $base = DocumentTransition::query()
            ->when($window, fn ($query, array $range) => $query
                ->where('document_transitions.created_at', '>=', $range[0])
                ->when($range[1] ?? null, fn ($q, $end) => $q->where('document_transitions.created_at', '<', $end)))
            ->when($from, fn ($query, $value) => $query->where('document_transitions.created_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($to, fn ($query, $value) => $query->where('document_transitions.created_at', '<', Carbon::parse($value)->addDay()->startOfDay()))
            ->when($formType, fn ($query, $value) => $query->whereHas(
                'document',
                fn ($q) => $q->where('form_type', $value)
            ))
            ->when($action, fn ($query, $value) => $query->where('document_transitions.action', $value))
            // Document title, organization name, or the name of the person who did it.
            ->when($search !== '', fn ($query) => $query->where(
                fn ($outer) => $outer
                    ->whereHas('document', fn ($q) => $q->where('title', 'like', "%{$search}%")
                        ->orWhereHas('organization', fn ($q2) => $q2->where('name', 'like', "%{$search}%")))
                    ->orWhereHas('actor', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ));

        $transitions = $base
            ->with([
                'actor:id,name',
                'document.organization.school',
                ...array_map(fn (string $relation) => "document.{$relation}", DocumentDisplayTitle::relations()),
            ])
            ->orderByDesc('document_transitions.created_at')
            ->orderByDesc('document_transitions.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $viewer = $request->user();

        return Inertia::render('admin/activity/index', [
            'transitions' => [
                'data' => collect($transitions->items())->map(function (DocumentTransition $t) use ($viewer) {
                    $document = $t->document;
                    $when = DisplayTimezone::convert($t->created_at->copy());

                    return [
                        'id' => $t->id,
                        'actorName' => $t->actor?->name ?? 'System',
                        'action' => $t->action->value,
                        'documentTitle' => DocumentDisplayTitle::subject($document),
                        'formTypeLabel' => $document->form_type->label(),
                        'organizationName' => $document->organization->name,
                        'college' => $document->organization->school?->name ?? School::NONE_LABEL,
                        'createdAt' => $t->created_at,
                        // Pre-formatted in Asia/Manila so the browser's own timezone never shifts it.
                        'whenDate' => $when->format('n/j/Y'),
                        'whenTime' => $when->format('g:i A'),
                        // Null when this admin may not open the document, so the title shows as plain text.
                        'href' => Gate::forUser($viewer)->allows('reviewView', $document)
                            ? route($document->form_type->reviewShowRouteName(), $document)
                            : null,
                    ];
                })->values(),
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
                'from' => $from,
                'to' => $to,
                'date' => $date,
            ],
            'formTypes' => collect(FormType::cases())
                ->map(fn (FormType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'actions' => collect(TransitionAction::cases())
                ->map(fn (TransitionAction $a) => ['value' => $a->value])
                ->values(),
            // Current-term figures; the filters above never narrow these.
            'stats' => $stats->summary(CurrentPeriod::get()),
        ]);
    }
}
