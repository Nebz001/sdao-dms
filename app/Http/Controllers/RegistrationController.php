<?php

namespace App\Http\Controllers;

use App\Approval\SectionFlags;
use App\Attachments\AttachmentSlots;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Http\Requests\Registrations\StoreRegistrationRequest;
use App\Http\Requests\Registrations\UpdateRegistrationRequest;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Registrations\SubmitOrganizationRegistration;
use App\Registrations\UpdateOrganizationRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /**
     * Max search results returned to the adviser typeahead.
     */
    private const int ADVISER_SEARCH_LIMIT = 10;

    private const int PER_PAGE = 20;

    /**
     * List: registrations for any org the user is an active officer of, PLUS
     * their own pending founding proposals (Phase 2 item 5) — a founding
     * student has no membership yet on their own not-yet-Approved proposal,
     * so the org-membership filter alone would hide it from them. Two
     * audiences share this one query on purpose, not by accident: for an
     * officer it surfaces their org's original registration record, and for
     * a not-yet-affiliated founding student it's the only way (besides the
     * notification bell) to check on a submission they can't act on yet —
     * the same row, resolved by whichever leg of the OR applies to the
     * viewer. Structured like DocumentHistoryController: same PER_PAGE, same
     * meta/links envelope, same clone-before-status-filter stats trick —
     * reused, not reinvented. Narrower than that one only in scope (always
     * OrganizationRegistration, so no form_type filter).
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $organizationIds = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        // Unrecognized filter values are treated as "no filter" rather than
        // trusted into the query — same defensive pattern as
        // DocumentHistoryController::index().
        $status = DocumentStatus::tryFrom($request->string('status')->toString())?->value;
        $search = $request->string('search')->trim()->toString();

        $base = Document::query()
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where(function ($query) use ($organizationIds, $user) {
                $query->whereIn('organization_id', $organizationIds)
                    ->orWhere('submitted_by', $user->id);
            })
            // Title only — it already embeds the org name (see
            // SubmitOrganizationRegistration::execute()), so this covers
            // org-name search too without a second predicate.
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"));

        // Reflects the search filter but NOT the status filter, so the stats
        // strip always shows what selecting each status would yield — same
        // trick as DocumentArchiveController/DocumentHistoryController.
        $counts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $documents = (clone $base)
            ->with('organization:id,name')
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('registrations/index', [
            'registrations' => [
                'data' => collect($documents->items())->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'status' => $d->status->value,
                    'organization' => ['id' => $d->organization->id, 'name' => $d->organization->name],
                    'created_at' => $d->created_at,
                    'href' => route('registrations.show', $d),
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
                'status' => $status,
                'search' => $search,
            ],
            // Values only — the client labels them with statusLabel() from
            // @/lib/utils, same idiom as DocumentHistoryController::index().
            'statuses' => collect(DocumentStatus::cases())
                ->map(fn (DocumentStatus $s) => ['value' => $s->value])
                ->values(),
            'stats' => [
                'total' => (int) $counts->sum(),
                'inProgress' => (int) ($counts[DocumentStatus::Draft->value] ?? 0)
                    + (int) ($counts[DocumentStatus::InReview->value] ?? 0)
                    + (int) ($counts[DocumentStatus::Returned->value] ?? 0),
                'approved' => (int) ($counts[DocumentStatus::Approved->value] ?? 0),
                'rejected' => (int) ($counts[DocumentStatus::Rejected->value] ?? 0),
            ],
        ]);
    }

    /**
     * Phase 2 item 5: a not-yet-affiliated Verified student proposes a
     * brand-new organization directly — no pre-existing org, no pre-existing
     * binding.
     */
    public function create(): Response
    {
        $canPropose = Gate::allows('propose', Organization::class);

        return Inertia::render('registrations/create', [
            'canPropose' => $canPropose,
            'schools' => School::query()
                ->with('programs')
                ->orderBy('name')
                ->get()
                ->map(fn (School $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'type' => $s->type,
                    'programs' => $s->programs->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
                ]),
            'organizationTypes' => collect(OrganizationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'attachmentSlots' => AttachmentSlots::slotsFor(FormType::OrganizationRegistration),
        ]);
    }

    public function store(StoreRegistrationRequest $request, SubmitOrganizationRegistration $action): RedirectResponse
    {
        $user = Auth::user();

        Gate::authorize('propose', Organization::class);

        $document = $action->execute(
            actor: $user,
            name: $request->string('name')->toString(),
            schoolId: $request->filled('school_id') ? $request->integer('school_id') : null,
            programId: $request->filled('program_id') ? $request->integer('program_id') : null,
            adviserId: $request->integer('adviser_id'),
            organizationType: OrganizationType::from($request->string('organization_type')->toString()),
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::OrganizationRegistration),
        );

        return redirect()->route('registrations.show', $document)
            ->with('flash', ['message' => 'Registration submitted for SDAO review.']);
    }

    /**
     * Live adviser typeahead (Phase 2 item 5) — mirrors
     * ActivityCalendarController::conflictCheck's debounced-fetch pattern.
     * Returns whether each matching adviser is currently available
     * (organization_id null) so the frontend can show a live warning; this
     * is a soft signal only, NOT the enforcement point (that's the
     * approve-time re-check in ApproveOrganizationRegistration).
     */
    public function adviserSearch(Request $request): JsonResponse
    {
        // Security gap fix: this was the only method on the class with no
        // authorization call, reachable by any authenticated, email-verified
        // account (including one still awaiting SDAO account verification) —
        // returned every adviser's name/email with no search term required.
        // Reuses the same ability store() already enforces on the submission
        // this typeahead feeds, rather than inventing a new one.
        Gate::authorize('propose', Organization::class);

        $search = $request->string('q')->trim()->toString();

        $advisers = User::query()
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::Adviser->value))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->limit(self::ADVISER_SEARCH_LIMIT)
            ->get(['id', 'name', 'email']);

        $assignments = RoleAssignment::query()
            ->where('role', Role::Adviser->value)
            ->whereIn('user_id', $advisers->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $results = $advisers->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'is_available' => ($assignments->get($u->id)?->organization_id) === null,
        ]);

        return response()->json(['advisers' => $results]);
    }

    public function show(Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail.adviser', 'transitions.actor', 'stepApprovals.user', 'attachments']);

        $detail = $document->registrationDetail;
        $attachments = AttachmentSlots::presentForDocument($document);

        return Inertia::render('registrations/show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status->value,
                'current_step_position' => $document->current_step_position,
                'submitted_by' => $document->submitted_by,
                'organization' => [
                    'id' => $document->organization->id,
                    'name' => $document->organization->name,
                    // Field-presence parity (Phase 2 item 7 slice 2) — College
                    // (relabeled School) and Program shown read-only everywhere.
                    'college' => $document->organization->school?->name,
                    'program' => $document->organization->program?->name,
                ],
            ],
            'detail' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'organization_type_label' => $detail->organization_type->label(),
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
                'adviser' => $detail->adviser ? ['name' => $detail->adviser->name] : null,
            ] : null,
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'history' => $document->transitions->map(fn ($t) => [
                'id' => $t->id,
                'action' => $t->action->value,
                'from_status' => $t->from_status?->value,
                'to_status' => $t->to_status->value,
                'step_position' => $t->step_position,
                'comment' => $t->comment,
                'flagged_sections' => $t->flagged_sections,
                'section_comments' => $t->section_comments,
                'field_changes' => $t->field_changes,
                'actor' => $t->actor ? ['name' => $t->actor->name] : null,
                'created_at' => $t->created_at,
            ]),
            'flaggedSectionLabels' => SectionFlags::labelsFor($document->form_type),
        ]);
    }

    public function edit(Document $document): Response
    {
        Gate::authorize('edit', $document);

        $document->load(['organization.school', 'organization.program', 'registrationDetail.adviser', 'attachments']);
        $detail = $document->registrationDetail;
        $attachments = AttachmentSlots::presentForDocument($document);
        $flaggedSections = SectionFlags::currentlyFlagged($document);

        return Inertia::render('registrations/edit', [
            'flaggedComment' => SectionFlags::currentComment($document),
            'flaggedSectionComments' => SectionFlags::currentSectionComments($document),
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'organization' => [
                    'name' => $document->organization->name,
                    // Field-presence parity (Phase 2 item 7 slice 2).
                    'college' => $document->organization->school?->name,
                    'program' => $document->organization->program?->name,
                ],
            ],
            'detail' => $detail ? [
                'organization_type' => $detail->organization_type->value,
                'purpose_of_organization' => $detail->purpose_of_organization,
                'contact_person' => $detail->contact_person,
                'contact_no' => $detail->contact_no,
                'email_address' => $detail->email_address,
                'date_organized' => $detail->date_organized?->toDateString(),
                'adviser' => $detail->adviser ? ['id' => $detail->adviser->id, 'name' => $detail->adviser->name] : null,
            ] : null,
            'organizationTypes' => collect(OrganizationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'attachmentSlots' => $attachments['slots'],
            'attachments' => $attachments['files'],
            'flaggedSections' => $flaggedSections,
        ]);
    }

    public function update(UpdateRegistrationRequest $request, Document $document, UpdateOrganizationRegistration $action): RedirectResponse
    {
        Gate::authorize('edit', $document);

        $action->execute(
            actor: Auth::user(),
            document: $document,
            organizationType: OrganizationType::from($request->string('organization_type')->toString()),
            purposeOfOrganization: $request->string('purpose_of_organization')->toString(),
            contactPerson: $request->string('contact_person')->toString(),
            contactNo: $request->string('contact_no')->toString(),
            emailAddress: $request->string('email_address')->toString(),
            dateOrganized: $request->string('date_organized')->toString(),
            attachmentFiles: AttachmentSlots::extractUploadedFiles($request, FormType::OrganizationRegistration),
            adviserId: $request->filled('adviser_id') ? $request->integer('adviser_id') : null,
        );

        return redirect()->route('registrations.show', $document)
            ->with('flash', ['message' => 'Registration resubmitted for SDAO review.']);
    }
}
