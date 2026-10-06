<?php

namespace App\Http\Controllers\Admin;

use App\Approval\Exceptions\ReminderNotAllowedException;
use App\Approval\StuckDocumentReminders;
use App\Dashboard\AdminAttentionData;
use App\Dashboard\DocumentDisplayTitle;
use App\Dashboard\InReviewSnapshot;
use App\Dashboard\StuckDocument;
use App\Dashboard\StuckDocumentStats;
use App\Enums\FormType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\School;
use App\Support\DisplayTimezone;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    /** The `idle` filter's allowed values: the idle buckets on the "How long idle" card. */
    private const array IDLE_BUCKETS = ['under_7', '7_14', '15_30', 'over_30'];

    public function index(Request $request, InReviewSnapshot $snapshot, AdminAttentionData $attention, StuckDocumentStats $stats, StuckDocumentReminders $reminders): Response
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
        $idle = in_array($request->string('idle')->toString(), self::IDLE_BUCKETS, true) ? $request->string('idle')->toString() : null;
        $search = $request->string('search')->trim()->toString();

        // An approver, role or approver-only filter can only match documents
        // sitting with an approver, so the returned set is left out for them.
        $approverOnly = $approver !== '' || $role !== null;

        // Every stuck document, unfiltered: the stat cards read all of it.
        $all = $snapshot->rows()->map(fn (StuckDocument $row) => $this->inReviewRow($row))
            ->concat($attention->returnedDocuments()->map(fn (Document $document) => $this->returnedRow($document)))
            ->values();

        $rows = $all
            ->when($waitingOn === 'org', fn (Collection $c) => $c->where('state', 'returned'))
            ->when($waitingOn === 'approver' || $approverOnly, fn (Collection $c) => $c->where('state', 'in_review'))
            ->when($approver !== '', fn (Collection $c) => $c->where('approverKey', $approver))
            ->when($role, fn (Collection $c) => $c->where('stepRole', $role))
            ->when($formType, fn (Collection $c) => $c->where('formType', $formType))
            ->when($idle, fn (Collection $c) => $c->filter(fn (array $row) => StuckDocumentStats::bucketFor($row['idleDays']) === $idle))
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn (array $row) => str_contains(mb_strtolower($row['title'].' '.$row['organizationName'].' '.$row['waitingOn']), mb_strtolower($search))
            ))
            ->sort(fn (array $a, array $b) => [$b['idleDays'], $a['since'], $a['id']] <=> [$a['idleDays'], $b['since'], $b['id']])
            ->values();

        $documents = $this->paginate($request, $rows->all());
        $cooldowns = $reminders->nextAvailableForMany(array_column($documents['data'], 'id'));
        $documents['data'] = array_map(fn (array $row) => [
            ...$row,
            'remindAvailableLabel' => isset($cooldowns[$row['id']])
                ? DisplayTimezone::convert($cooldowns[$row['id']]->copy())->format('n/j/Y g:i A')
                : null,
        ], $documents['data']);

        return Inertia::render('admin/stuck-documents/index', [
            'mode' => 'documents',
            'documents' => $documents,
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
            'stats' => $stats->summary($all),
        ]);
    }

    /**
     * Sends a reminder to whoever the document is waiting on. The route sits
     * behind `can:access-admin`; the check is repeated here and inside the
     * action, so the button being visible is never the only guard.
     */
    public function remind(Document $document, StuckDocumentReminders $reminders): RedirectResponse
    {
        Gate::authorize('access-admin');

        try {
            $recipients = $reminders->send(Auth::user(), $document);
        } catch (ReminderNotAllowedException $e) {
            return back()->with('flash', FlashToast::error('Reminder not sent', $e->getMessage()));
        }

        $names = $recipients->count() <= 2
            ? $recipients->pluck('name')->join(' and ')
            : $recipients->count().' people';

        return back()->with('flash', FlashToast::make('Reminder sent', "{$names} will get a reminder about this document."));
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
            'stats' => app(StuckDocumentStats::class)->summary(collect()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inReviewRow(StuckDocument $row): array
    {
        $document = $row->document;
        $stepCount = $document->workflowTemplate?->steps->count();
        $roleLabel = $row->stepRole === Role::SdaoMember ? 'SDAO' : $row->stepRole->label();

        return [
            ...$this->commonFields($document, $row->idleDays),
            'state' => 'in_review',
            // Who the Remind button will message, in words for its confirmation.
            'remindTo' => $row->isSdaoStep() ? 'the SDAO members who have not approved it yet' : $row->approverName,
            'waitingOn' => $row->approverName,
            'waitingOnLine' => $stepCount
                ? "{$roleLabel}, step {$document->current_step_position} of {$stepCount}"
                : $roleLabel,
            'approverKey' => $row->approverKey,
            'approverRole' => $row->stepRole->label(),
            'stepRole' => $row->stepRole->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function returnedRow(Document $document): array
    {
        return [
            ...$this->commonFields($document, InReviewSnapshot::idleDays($document)),
            'state' => 'returned',
            'remindTo' => "the president and secretary of {$document->organization->name}",
            'waitingOn' => 'The organization',
            'waitingOnLine' => 'Returned for revision',
            'approverKey' => null,
            'approverRole' => null,
            'stepRole' => null,
        ];
    }

    /**
     * The fields every row shares. `since` is when the document reached its
     * current holder: its latest transition, never documents.updated_at.
     *
     * @return array<string, mixed>
     */
    private function commonFields(Document $document, int $idleDays): array
    {
        $since = $document->latestTransition?->created_at ?? $document->created_at;

        return [
            'id' => $document->id,
            'title' => DocumentDisplayTitle::bareTitle($document),
            'formType' => $document->form_type->value,
            'formTypeLabel' => $document->form_type->label(),
            'organizationName' => $document->organization->name,
            'college' => $document->organization->school?->name ?? School::NONE_LABEL,
            'since' => $since->toIso8601String(),
            'sinceDate' => DisplayTimezone::convert($since->copy())->format('n/j/Y'),
            'idleDays' => $idleDays,
            'idleTone' => StuckDocumentStats::toneFor($idleDays),
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
