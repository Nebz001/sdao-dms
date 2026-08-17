<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\OrganizationMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every document an officer's organization has ever filed — all five form
 * types, all five statuses, Draft through Rejected. The five per-type
 * index() methods (RegistrationController etc.) each show one form type and
 * mostly just the in-flight ones; this is the org's whole paper trail in one
 * filterable, paginated place. Structured identically to
 * DocumentArchiveController: same filter-bar shape, same PER_PAGE, same
 * meta/links envelope — reused, not reinvented. The difference is scope:
 * that one is SDAO-wide and `access-admin`-gated, terminal-statuses-only;
 * this one is scoped to the viewer's own organization and spans every
 * status on purpose.
 *
 * Authorization is the query scope itself, not a Gate check layered on top.
 * The `where` below is a literal transcription of
 * OrganizationMembershipService::canActOnDocument() — the first and
 * broadest clause of DocumentPolicy::view(), which every one of the five
 * show() controllers already enforces on click-through — so this list can
 * never surface a row the viewer would then be 403'd out of. Deliberately
 * NOT followed by a ->filter(fn ($d) => Gate::allows('view', $d)) post-filter
 * the way the four review queues are (see RegistrationReviewController):
 * those queues have no actor-derived SQL predicate at all, so the Gate
 * filter is their only boundary, and they're small and unpaginated so
 * filtering after get() is safe. Here the SQL scope already IS the
 * boundary (an organization_id drawn from the viewer's own active
 * membership can never resolve to a foreign org), and this list is
 * paginated — filtering after paginate() would desynchronize meta.total
 * from the rows actually shown, for no security benefit. DocumentHistoryTest
 * pins the equivalence instead (every returned row also passes
 * Gate::allows('view')), catching any future drift at test time.
 *
 * The submitted_by leg isn't redundant: a founding student has no
 * OrganizationMembership row yet on their own pending registration (Phase 2
 * item 5) — binding happens only at SDAO approval — exactly the case
 * RegistrationController::index() already carries this same clause for.
 */
class DocumentHistoryController extends Controller
{
    private const int PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $user = Auth::user();

        $organizationIds = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // DocumentArchiveController::index(). Unlike that archive, every
        // DocumentStatus case is selectable here: this page's whole point is
        // the non-terminal ones the archive deliberately excludes.
        $formType = FormType::tryFrom($request->string('form_type')->toString())?->value;
        $status = DocumentStatus::tryFrom($request->string('status')->toString())?->value;
        $search = $request->string('search')->trim()->toString();

        $base = Document::query()
            ->where(function ($query) use ($organizationIds, $user) {
                $query->whereIn('organization_id', $organizationIds)
                    ->orWhere('submitted_by', $user->id);
            })
            ->when($formType, fn ($query, $value) => $query->where('form_type', $value))
            // Title only — a single-organization page has no second name to
            // search on, unlike the org-spanning archive and activity log.
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"));

        // Reflects the form-type/search filters but NOT the status filter,
        // so the status counts always show what selecting each one would
        // yield — same trick as DocumentArchiveController::index().
        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documents = (clone $base)
            ->with(['organization:id,name', 'latestTransition'])
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $inProgress = (int) ($counts[DocumentStatus::Draft->value] ?? 0)
            + (int) ($counts[DocumentStatus::InReview->value] ?? 0)
            + (int) ($counts[DocumentStatus::Returned->value] ?? 0);

        return Inertia::render('document-history/index', [
            'documents' => [
                'data' => collect($documents->items())->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'status' => $d->status->value,
                    'formType' => $d->form_type->value,
                    'formTypeLabel' => $d->form_type->label(),
                    // documents.updated_at isn't a reliable "last activity"
                    // clock here (a partial SDAO approval writes a
                    // transition without saving the Document row — see
                    // Document::latestTransition()'s own docblock), so the
                    // displayed timestamp reads from the transition log,
                    // same idiom as AdminDashboardController's "needs
                    // attention" panel. A true zero-transition Draft falls
                    // back to when it was created.
                    'lastActivityAt' => $d->latestTransition?->created_at ?? $d->created_at,
                    'href' => route($d->form_type->studentShowRouteName(), $d),
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
            // Values only — the client labels them with statusLabel() from
            // @/lib/utils, the same helper StatusBadge already uses, rather
            // than a second label source on the server.
            'statuses' => collect(DocumentStatus::cases())
                ->map(fn (DocumentStatus $s) => ['value' => $s->value])
                ->values(),
            'stats' => [
                'total' => (int) $counts->sum(),
                'inProgress' => $inProgress,
                'approved' => (int) ($counts[DocumentStatus::Approved->value] ?? 0),
                'rejected' => (int) ($counts[DocumentStatus::Rejected->value] ?? 0),
            ],
        ]);
    }
}
