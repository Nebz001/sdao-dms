<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Reported gap: once a document reaches a terminal status it "vanishes" from
 * the admin side — every review queue is deliberately InReview-only
 * (RegistrationReviewController::index() etc.), and nothing else lists a
 * document once it leaves them. DocumentArchiveController::index() is the
 * fix: a single cross-form-type list of Approved/Rejected documents, gated by
 * the existing `access-admin` gate.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/**
 * Directly-created terminal document — the same precedent
 * DocumentViewAuthorizationTest's viewAuthApprovedCalendarActivity() uses —
 * for tests about the archive's query/filter behavior, not approval
 * mechanics.
 */
function archivedDocument(FormType $formType, Organization $org, DocumentStatus $status, string $title): Document
{
    return Document::factory()->create([
        'form_type' => $formType,
        'organization_id' => $org->id,
        'status' => $status,
        'current_step_position' => null,
        'title' => $title,
    ]);
}

test('the archive lists approved and rejected documents for every form type', function (FormType $formType) {
    archivedDocument($formType, $this->org, DocumentStatus::Approved, 'Approved One');
    archivedDocument($formType, $this->org, DocumentStatus::Rejected, 'Rejected One');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/archive/index')
            ->where('documents.data.0.form_type', $formType->value)
            ->has('documents.data', 2)
        );
})->with([
    'registration' => [FormType::OrganizationRegistration],
    'renewal' => [FormType::OrganizationRenewal],
    'activity calendar' => [FormType::ActivityCalendar],
    'activity proposal' => [FormType::ActivityProposal],
    'after-activity report' => [FormType::AfterActivityReport],
]);

test('the archive excludes draft, in-review, and returned documents', function (DocumentStatus $status) {
    archivedDocument(FormType::OrganizationRegistration, $this->org, $status, 'Not Yet Decided');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 0));
})->with([
    'draft' => [DocumentStatus::Draft],
    'in review' => [DocumentStatus::InReview],
    'returned' => [DocumentStatus::Returned],
]);

test('an approved document is absent from the review queue but present in the archive', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $sdaoB);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('queue', 0));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $doc->id)
            ->where('documents.data.0.status', 'approved')
            ->where('documents.data.0.href', route('review.registrations.show', $doc))
        );
});

test('the form_type filter narrows the result set', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'A Registration');
    archivedDocument(FormType::OrganizationRenewal, $this->org, DocumentStatus::Approved, 'A Renewal');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['form_type' => FormType::OrganizationRenewal->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.form_type', FormType::OrganizationRenewal->value)
        );
});

test('the status filter narrows the result set', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Approved Doc');
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Rejected, 'Rejected Doc');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['status' => DocumentStatus::Rejected->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.status', 'rejected')
        );
});

test('search matches the document title or the organization name', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Findable Title');
    $other = archivedDocument(FormType::OrganizationRegistration, $this->itGuild, DocumentStatus::Approved, 'Something Else');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['search' => 'IT Guild']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $other->id)
        );
});

test('an unknown form_type or status filter value is ignored rather than emptying the page', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Still Here');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['form_type' => 'not_a_real_type', 'status' => 'also_bogus']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));
});

test('results are paginated at 20 per page, newest-decided first', function () {
    foreach (range(1, 25) as $i) {
        $doc = archivedDocument(FormType::ActivityCalendar, $this->org, DocumentStatus::Approved, "Doc {$i}");
        // No transitions here, so decided_at falls back to updated_at. Force
        // distinct, increasing values so ordering is deterministic rather than
        // relying on same-second factory timestamps.
        $doc->forceFill(['updated_at' => now()->addSeconds($i)])->save();
    }

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 20)
            ->where('documents.meta.last_page', 2)
            ->where('documents.meta.total', 25)
            ->where('documents.data.0.title', 'Doc 25')
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 5));
});

/** Records the final transition that moved a document into a terminal status. */
function decidedAt(Document $doc, CarbonInterface $at, DocumentStatus $status): void
{
    DocumentTransition::factory()->create([
        'document_id' => $doc->id,
        'to_status' => $status,
        'action' => $status === DocumentStatus::Approved ? TransitionAction::Completed : TransitionAction::Rejected,
        'created_at' => $at,
    ]);
}

test('stats cover the whole archive and ignore the filters', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Approved Doc');
    archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Approved, 'Approved Doc 2');
    archivedDocument(FormType::ActivityProposal, $this->itGuild, DocumentStatus::Rejected, 'Rejected Doc');
    archivedDocument(FormType::ActivityProposal, $this->itGuild, DocumentStatus::InReview, 'Still In Review');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['status' => 'rejected', 'form_type' => 'activity_proposal', 'search' => 'IT Guild']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('stats.total', ['total' => 3, 'approved' => 2, 'rejected' => 1])
        );
});

test('by form type lists all five types, highest count first, zero counts included', function () {
    archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Approved, 'P1');
    archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Rejected, 'P2');
    archivedDocument(FormType::AfterActivityReport, $this->org, DocumentStatus::Approved, 'R1');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('stats.byFormType', 5)
            ->where('stats.byFormType.0', ['form_type' => 'activity_proposal', 'label' => 'Activity Proposal', 'count' => 2])
            ->where('stats.byFormType.1', ['form_type' => 'after_activity_report', 'label' => 'After-Activity Report', 'count' => 1])
            // Zero counts keep the declared form type order.
            ->where('stats.byFormType.2', ['form_type' => 'organization_registration', 'label' => 'Registration', 'count' => 0])
            ->where('stats.byFormType.3.label', 'Renewal')
            ->where('stats.byFormType.4.label', 'Activity Calendar')
        );
});

test('decided per week buckets the last 8 Monday-start weeks and counts this week', function () {
    $this->travelTo(now()->setDate(2026, 9, 16)->setTime(12, 0)); // a Wednesday
    $monday = now()->startOfWeek();

    $inWeek = function (int $weeksAgo, int $count, DocumentStatus $status = DocumentStatus::Approved) use ($monday) {
        foreach (range(1, $count) as $_) {
            $doc = archivedDocument(FormType::ActivityCalendar, $this->org, $status, 'W');
            decidedAt($doc, $monday->copy()->subWeeks($weeksAgo)->addDay(), $status);
        }
    };
    $inWeek(0, 2);
    $inWeek(0, 1, DocumentStatus::Rejected);
    $inWeek(1, 1);
    $inWeek(7, 4);
    $inWeek(8, 5); // outside the 8-week window

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.perWeek.weeks', [4, 0, 0, 0, 0, 0, 1, 3])
            ->where('stats.perWeek.thisWeek', 3)
        );
});

test('decided on is the final approve or reject transition, not updated_at', function () {
    $doc = archivedDocument(FormType::ActivityCalendar, $this->org, DocumentStatus::Approved, 'Dated');
    DocumentTransition::factory()->create(['document_id' => $doc->id, 'to_status' => DocumentStatus::InReview, 'created_at' => '2026-01-01 08:00:00']);
    DocumentTransition::factory()->create(['document_id' => $doc->id, 'to_status' => DocumentStatus::Approved, 'created_at' => '2026-03-05 09:30:00']);
    $doc->forceFill(['updated_at' => '2026-09-30 10:00:00'])->saveQuietly();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.decided_at', fn ($value) => str_starts_with((string) $value, '2026-03-05'))
        );
});

test('the table is ordered by the decision time, not updated_at', function () {
    $early = archivedDocument(FormType::ActivityCalendar, $this->org, DocumentStatus::Approved, 'Decided early');
    $late = archivedDocument(FormType::ActivityCalendar, $this->org, DocumentStatus::Rejected, 'Decided late');
    decidedAt($early, now()->subDays(10), DocumentStatus::Approved);
    decidedAt($late, now()->subDay(), DocumentStatus::Rejected);
    // updated_at says the opposite; it must be ignored.
    $early->forceFill(['updated_at' => now()->addDay()])->saveQuietly();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data.0.id', $late->id)
            ->where('documents.data.1.id', $early->id)
        );
});

test('most documents picks the organization with the most decided documents', function () {
    foreach ([DocumentStatus::Approved, DocumentStatus::Approved, DocumentStatus::Rejected] as $status) {
        archivedDocument(FormType::ActivityProposal, $this->itGuild, $status, 'IT');
    }
    archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Approved, 'Other');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.topOrganization.id', $this->itGuild->id)
            ->where('stats.topOrganization.name', 'IT Guild')
            ->where('stats.topOrganization.approved', 2)
            ->where('stats.topOrganization.rejected', 1)
        );
});

test('most documents breaks a tie by organization name, then id', function () {
    archivedDocument(FormType::ActivityProposal, $this->itGuild, DocumentStatus::Rejected, 'B');
    archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Approved, 'A');

    // "Computing Society" sorts before "IT Guild", whichever was created first.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertInertia(fn ($page) => $page->where('stats.topOrganization.name', 'Computing Society'));
});

test('most documents is null when nothing has been decided', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.topOrganization', null)
            ->where('stats.total.total', 0)
            ->where('stats.perWeek.thisWeek', 0)
        );
});

test('row titles drop the form type prefix and the organization suffix', function () {
    $registration = archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Organization Registration — Computing Society (2026-2027)');
    $renewal = archivedDocument(FormType::OrganizationRenewal, $this->org, DocumentStatus::Approved, 'Organization Renewal — Computing Society (2027-2028)');
    $orphanProposal = archivedDocument(FormType::ActivityProposal, $this->org, DocumentStatus::Approved, 'Activity Proposal — Career Fair (Computing Society)');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data', function ($rows) use ($registration, $renewal, $orphanProposal) {
                $titles = collect($rows)->pluck('title', 'id');

                return $titles[$registration->id] === 'Computing Society'
                    && $titles[$renewal->id] === 'Computing Society'
                    // No proposal row: falls back to the stored title, stripped.
                    && $titles[$orphanProposal->id] === 'Career Fair';
            })
        );
});

test('each row carries the organization college, or "No college"', function () {
    $withSchool = archivedDocument(FormType::ActivityCalendar, $this->org, DocumentStatus::Approved, 'Has School');
    $this->itGuild->forceFill(['school_id' => null, 'program_id' => null])->save();
    $without = archivedDocument(FormType::ActivityCalendar, $this->itGuild, DocumentStatus::Approved, 'No School');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data', function ($rows) use ($withSchool, $without) {
                $colleges = collect($rows)->pluck('college', 'id');

                return $colleges[$without->id] === 'No college'
                    && $colleges[$withSchool->id] !== 'No college';
            })
        );
});
