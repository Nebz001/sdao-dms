<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentArchiveController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * The two terminal statuses (DocumentStatus::isTerminal()) — everything
     * this archive exists to surface, since the five review queues are
     * deliberately InReview-only (RegistrationReviewController::index() etc.)
     * and nothing else lists a document once it leaves them.
     *
     * @var array<int, string>
     */
    private const ARCHIVED_STATUSES = [
        DocumentStatus::Approved->value,
        DocumentStatus::Rejected->value,
    ];

    /**
     * Maps a document's form type to its approver-facing "show" route name —
     * the archive reuses the existing review show pages (they already render
     * full history and already hide approve/reject/return once a document is
     * terminal via `isInReview` + `reviewOnlyStatusNote()`) rather than
     * building a duplicate read-only page.
     *
     * @var array<string, string>
     */
    private const REVIEW_SHOW_ROUTE_NAMES = [
        'organization_registration' => 'review.registrations.show',
        'organization_renewal' => 'review.renewals.show',
        'activity_calendar' => 'review.activity-calendars.show',
        'activity_proposal' => 'review.activity-proposals.show',
        'after_activity_report' => 'review.reports.show',
    ];

    public function index(Request $request): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — an unknown form_type/status should not
        // silently produce an empty page.
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $status = collect(self::ARCHIVED_STATUSES)->contains($request->string('status')->toString())
            ? $request->string('status')->toString()
            : null;
        $search = $request->string('search')->trim()->toString();

        $base = Document::query()
            ->whereIn('status', self::ARCHIVED_STATUSES)
            ->when($formType, fn ($query, $value) => $query->where('form_type', $value))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
            ));

        // Reflects the current form-type/search filters but NOT the status
        // filter, so the Approved/Rejected counts always show what selecting
        // them would yield.
        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documents = (clone $base)
            ->with('organization:id,name')
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            // documents.updated_at is a faithful "decided at" for a terminal
            // document: DocumentPolicy::edit() requires status = Returned, so
            // nothing ever writes to a row again after the finalizing update
            // that set it to Approved/Rejected.
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/archive/index', [
            'documents' => [
                'data' => collect($documents->items())->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'status' => $d->status->value,
                    'form_type' => $d->form_type->value,
                    'form_type_label' => $d->form_type->label(),
                    'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                    'decided_at' => $d->updated_at,
                    'href' => route(self::REVIEW_SHOW_ROUTE_NAMES[$d->form_type->value], $d),
                ])->values(),
                'meta' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'from' => $documents->firstItem(),
                    'to' => $documents->lastItem(),
                    'total' => $documents->total(),
                ],
                'links' => [
                    'prev' => $documents->previousPageUrl(),
                    'next' => $documents->nextPageUrl(),
                ],
            ],
            'filters' => [
                'form_type' => $formType,
                'status' => $status,
                'search' => $search,
            ],
            'formTypes' => collect(FormType::cases())
                ->map(fn (FormType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'stats' => [
                'approved' => (int) ($counts[DocumentStatus::Approved->value] ?? 0),
                'rejected' => (int) ($counts[DocumentStatus::Rejected->value] ?? 0),
                'total' => (int) $counts->sum(),
            ],
        ]);
    }
}
