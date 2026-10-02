<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\AdminAttentionData;
use App\Dashboard\DocumentDisplayTitle;
use App\Dashboard\InReviewSnapshot;
use App\Dashboard\StuckDocument;
use App\Enums\FormType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The destination behind the dashboard's "View all", tiles and split bar: every
 * open document (in review with an approver, or returned to the organization),
 * filterable by who it is waiting on. Reached only through those links, never
 * from the sidebar. The `view=upcoming` mode lists the activities behind the
 * dashboard's "happening within 7 days" alert instead.
 *
 * Reads the same InReviewSnapshot and returned set as the dashboard, so the
 * counts here always match the numbers that linked to this page.
 */
class StuckDocumentsController extends Controller
{
    private const int PER_PAGE = 20;

    /** The `idle` filter's allowed day thresholds. */
    private const array IDLE_THRESHOLDS = [3, 7];

    public function index(Request $request, InReviewSnapshot $snapshot, AdminAttentionData $attention): Response
    {
        if ($request->string('view')->toString() === 'upcoming') {
            return $this->upcoming($request, $attention);
        }

        // Unrecognized filter values are treated as "no filter", like the
        // archive and activity log pages.
        $waitingOn = in_array($request->string('waiting_on')->toString(), ['approver', 'org'], true)
            ? $request->string('waiting_on')->toString()
            : null;
        $approver = $request->string('approver')->trim()->toString();
        $role = Role::tryFrom($request->string('role')->toString())?->value;
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $idle = in_array($request->integer('idle'), self::IDLE_THRESHOLDS, true) ? $request->integer('idle') : null;
        $search = $request->string('search')->trim()->toString();

        // An approver, role or approver-only filter can only match documents
        // sitting with an approver, so the returned set is left out for them.
        $approverOnly = $approver !== '' || $role !== null;

        $inReview = $waitingOn === 'org' ? collect() : $snapshot->rows()
            ->when($approver !== '', fn (Collection $rows) => $rows->where('approverKey', $approver))
            ->when($role, fn (Collection $rows) => $rows->filter(fn (StuckDocument $row) => $row->stepRole->value === $role))
            ->map(fn (StuckDocument $row) => $this->inReviewRow($row));

        $returned = ($waitingOn === 'approver' || $approverOnly) ? collect() : $attention->returnedDocuments()
            ->map(fn (Document $document) => $this->returnedRow($document));

        $rows = $inReview->concat($returned)
            ->when($formType, fn (Collection $all) => $all->where('formType', $formType))
            ->when($idle, fn (Collection $all) => $all->where('idleDays', '>=', $idle))
            ->when($search !== '', fn (Collection $all) => $all->filter(
                fn (array $row) => str_contains(mb_strtolower($row['title'].' '.$row['organizationName'].' '.$row['waitingOn']), mb_strtolower($search))
            ))
            ->sortByDesc('idleDays')
            ->values();

        return Inertia::render('admin/stuck-documents/index', [
            'mode' => 'documents',
            'documents' => $this->paginate($request, $rows->all()),
            'activities' => null,
            'filters' => [
                'waiting_on' => $waitingOn,
                'approver' => $approver !== '' ? $approver : null,
                'role' => $role,
                'form_type' => $formType,
                'idle' => $idle,
                'search' => $search,
            ],
            'approvers' => $snapshot->groups()->map(fn (array $group) => ['key' => $group['key'], 'name' => $group['name'], 'line' => $group['line']])->values(),
            'formTypes' => collect(FormType::cases())
                ->map(fn (FormType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->values(),
            'stats' => [
                'withApprovers' => $snapshot->rows()->count(),
                'returned' => $attention->returnedDocuments()->count(),
            ],
        ]);
    }

    private function upcoming(Request $request, AdminAttentionData $attention): Response
    {
        return Inertia::render('admin/stuck-documents/index', [
            'mode' => 'upcoming',
            'documents' => null,
            'activities' => $this->paginate($request, $attention->upcomingUnapprovedActivities()->all()),
            'filters' => [
                'waiting_on' => null,
                'approver' => null,
                'role' => null,
                'form_type' => null,
                'idle' => null,
                'search' => '',
            ],
            'approvers' => [],
            'formTypes' => [],
            'stats' => ['withApprovers' => 0, 'returned' => 0],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inReviewRow(StuckDocument $row): array
    {
        return [
            'id' => $row->document->id,
            'title' => DocumentDisplayTitle::for($row->document),
            'formType' => $row->document->form_type->value,
            'organizationName' => $row->document->organization->name,
            'state' => 'in_review',
            'waitingOn' => $row->approverName,
            'waitingOnLine' => $row->approverLine,
            'idleDays' => $row->idleDays,
            'tier' => $row->tier,
            'href' => DocumentDisplayTitle::href($row->document),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function returnedRow(Document $document): array
    {
        $idleDays = InReviewSnapshot::idleDays($document);

        return [
            'id' => $document->id,
            'title' => DocumentDisplayTitle::for($document),
            'formType' => $document->form_type->value,
            'organizationName' => $document->organization->name,
            'state' => 'returned',
            'waitingOn' => 'Organization officers',
            'waitingOnLine' => $document->organization->name,
            'idleDays' => $idleDays,
            'tier' => InReviewSnapshot::tierFor($idleDays),
            'href' => DocumentDisplayTitle::href($document),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int|null>, links: array<string, string|null>}
     */
    private function paginate(Request $request, array $rows): array
    {
        $page = max(1, $request->integer('page', 1));
        $items = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $paginator = new LengthAwarePaginator($items, count($rows), self::PER_PAGE, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return [
            'data' => $items,
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
        ];
    }
}
