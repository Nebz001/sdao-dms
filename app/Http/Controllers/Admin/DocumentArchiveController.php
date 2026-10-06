<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\DocumentArchiveStats;
use App\Dashboard\DocumentDisplayTitle;
use App\Enums\FormType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\School;
use App\Support\CurrentPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DocumentArchiveController extends Controller
{
    private const int PER_PAGE = 20;

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

    public function index(Request $request, DocumentArchiveStats $stats): Response
    {
        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — an unknown form_type/status should not
        // silently produce an empty page.
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $status = collect(DocumentArchiveStats::ARCHIVED_STATUSES)->contains($request->string('status')->toString())
            ? $request->string('status')->toString()
            : null;
        $search = $request->string('search')->trim()->toString();
        // `academic_year=current` is the dashboard donut's destination: the same
        // created-this-academic-year window the donut counts, so the numbers match.
        $currentYearOnly = $request->string('academic_year')->toString() === 'current';

        // The archive scope: every Approved/Rejected document, each with its
        // `decided_at` (the final approve or reject transition).
        $documents = DocumentArchiveStats::archivedWithDecidedAt()
            ->when($currentYearOnly, fn ($query) => $query->whereBetween('documents.created_at', CurrentPeriod::get()->academicYearRange()))
            ->when($formType, fn ($query, $value) => $query->where('documents.form_type', $value))
            ->when($status, fn ($query, $value) => $query->where('documents.status', $value))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('documents.title', 'like', "%{$search}%")
                    ->orWhereHas('organization', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
            ))
            ->with([...DocumentDisplayTitle::relations(), 'organization.school'])
            ->orderByDesc('decided_at')
            ->orderByDesc('documents.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/archive/index', [
            'documents' => [
                'data' => collect($documents->items())->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => DocumentDisplayTitle::subject($d),
                    'status' => $d->status->value,
                    'form_type' => $d->form_type->value,
                    'form_type_label' => $d->form_type->label(),
                    'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                    'college' => $d->organization->school?->name ?? School::NONE_LABEL,
                    'decided_at' => Date::parse($d->getAttribute('decided_at')),
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
                'academic_year' => $currentYearOnly ? 'current' : null,
            ],
            'formTypes' => collect(FormType::cases())
                ->map(fn (FormType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            // Whole-archive figures: the filters above never narrow these.
            'stats' => $stats->summary(),
        ]);
    }
}
