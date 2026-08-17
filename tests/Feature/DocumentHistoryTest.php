<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Gate;

/**
 * DocumentHistoryController::index() — every document an officer's
 * organization has ever filed, across all five form types and every status,
 * not just the currently in-flight ones. Scoping reuses
 * OrganizationMembershipService::canActOnDocument()'s exact predicate (see
 * the controller's own docblock) rather than a new ad hoc check — these
 * tests exist primarily to pin that boundary, the same way
 * ReviewQueueAuthorizationTest pins the review queues' boundary.
 *
 * Fixtures: MembershipSeeder already binds Student Alpha as President and
 * Student Delta as Secretary of Computing Society (equal partners,
 * CLAUDE.md), and Student Beta as IT Guild's President — the ready-made
 * shared-ownership and cross-org pair, same trio CoOfficerDocumentAccessTest
 * and RegistrationsIndexTest use.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->otherOrgOfficer = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(); // IT Guild
});

/**
 * A document in an exact status, created directly — same precedent as
 * DocumentArchiveTest's archivedDocument(). These tests are about the
 * history page's scoping/filter/pagination behavior, not approval
 * mechanics; the one test that needs a real transition drives the actual
 * ApprovalEngine.
 */
function historyDocument(
    FormType $formType,
    Organization $org,
    DocumentStatus $status,
    string $title,
    ?User $submitter = null,
): Document {
    return Document::factory()->create([
        'form_type' => $formType,
        'organization_id' => $org->id,
        'status' => $status,
        'current_step_position' => null,
        'title' => $title,
        'submitted_by' => $submitter?->id,
    ]);
}

test('an officer sees documents across every form type and every status for their organization', function () {
    historyDocument(FormType::ActivityProposal, $this->computingSociety, DocumentStatus::Draft, 'Draft Proposal', $this->president);
    historyDocument(FormType::ActivityCalendar, $this->computingSociety, DocumentStatus::InReview, 'In Review Calendar', $this->president);
    historyDocument(FormType::OrganizationRenewal, $this->computingSociety, DocumentStatus::Returned, 'Returned Renewal', $this->president);
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Approved Registration', $this->president);
    historyDocument(FormType::AfterActivityReport, $this->computingSociety, DocumentStatus::Rejected, 'Rejected Report', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('document-history/index')
            ->has('documents.data', 5)
            ->where('documents.data', fn ($rows) => $rows->pluck('status')->sort()->values()->all()
                === ['approved', 'draft', 'in_review', 'rejected', 'returned'])
            ->where('documents.data', fn ($rows) => $rows->pluck('formType')->sort()->values()->all()
                === ['activity_calendar', 'activity_proposal', 'after_activity_report', 'organization_registration', 'organization_renewal'])
            ->where('stats.total', 5)
            ->where('stats.inProgress', 3)
            ->where('stats.approved', 1)
            ->where('stats.rejected', 1)
            ->etc()
        );
});

test('the president and the secretary of the same organization see the identical list', function () {
    // Guards the fixture itself: this test is meaningless if MembershipSeeder
    // ever stops providing a real president/secretary pair on one org.
    expect($this->president->organizationMemberships()->active()->firstOrFail()->position)
        ->toBe(OfficerPosition::President);
    expect($this->secretary->organizationMemberships()->active()->firstOrFail()->position)
        ->toBe(OfficerPosition::Secretary);

    $expected = collect([
        historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Registration', $this->president),
        historyDocument(FormType::ActivityProposal, $this->computingSociety, DocumentStatus::Draft, 'Draft Proposal', $this->president),
        // Filed by the secretary — the president must see it too, and vice versa.
        historyDocument(FormType::AfterActivityReport, $this->computingSociety, DocumentStatus::Returned, 'Report', $this->secretary),
    ])->pluck('id')->sort()->values()->all();

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $expected)
            ->etc()
        );

    $this->actingAs($this->secretary)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $expected)
            ->etc()
        );
});

test('an officer of another organization sees none of this organization\'s documents', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Computing Society Doc', $this->president);
    historyDocument(FormType::ActivityCalendar, $this->computingSociety, DocumentStatus::InReview, 'Computing Society Calendar', $this->president);
    $own = historyDocument(FormType::OrganizationRegistration, $this->itGuild, DocumentStatus::Approved, 'IT Guild Doc', $this->otherOrgOfficer);

    $this->actingAs($this->otherOrgOfficer)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $own->id)
            ->where('documents.data.0.title', 'IT Guild Doc')
            ->where('stats.total', 1)
            ->etc()
        );
});

/**
 * The query-level org scope is this page's only authorization boundary — it
 * deliberately carries no Gate::allows('view') post-filter (see the
 * controller's docblock: filtering a paginated result set in PHP would
 * desynchronise meta.total from the rows shown). This test is what makes
 * that safe: it pins that the SQL scope never returns a row
 * DocumentPolicy::view() would 403 on, so the two can't drift silently.
 */
test('every row the page returns is a document the viewer is also authorized to open', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Own Approved', $this->president);
    historyDocument(FormType::ActivityProposal, $this->computingSociety, DocumentStatus::Draft, 'Own Draft', $this->president);
    historyDocument(FormType::OrganizationRenewal, $this->computingSociety, DocumentStatus::Returned, 'Own Returned', $this->secretary);
    historyDocument(FormType::OrganizationRegistration, $this->itGuild, DocumentStatus::Approved, 'Foreign Doc', $this->otherOrgOfficer);

    foreach ([$this->president, $this->secretary] as $officer) {
        $this->actingAs($officer)->withoutVite()
            ->get(route('document-history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('documents.data', fn ($rows) => $rows->isNotEmpty()
                    && $rows->every(
                        fn ($row) => Gate::forUser($officer)->allows('view', Document::findOrFail($row['id'])),
                    ))
                ->etc()
            );
    }
});

test('the form type filter narrows the result set', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'A Registration', $this->president);
    historyDocument(FormType::OrganizationRenewal, $this->computingSociety, DocumentStatus::Approved, 'A Renewal', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index', ['form_type' => FormType::OrganizationRenewal->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'A Renewal')
            ->etc()
        );
});

test('the status filter narrows the result set, including the non-terminal statuses', function (DocumentStatus $status, string $expectedTitle) {
    historyDocument(FormType::ActivityProposal, $this->computingSociety, DocumentStatus::Draft, 'Draft Doc', $this->president);
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::InReview, 'In Review Doc', $this->president);
    historyDocument(FormType::OrganizationRenewal, $this->computingSociety, DocumentStatus::Returned, 'Returned Doc', $this->president);
    historyDocument(FormType::ActivityCalendar, $this->computingSociety, DocumentStatus::Approved, 'Approved Doc', $this->president);
    historyDocument(FormType::AfterActivityReport, $this->computingSociety, DocumentStatus::Rejected, 'Rejected Doc', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index', ['status' => $status->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.title', $expectedTitle)
            // Counts ignore the status filter, so the other four still count.
            ->where('stats.total', 5)
            ->etc()
        );
})->with([
    'draft' => [DocumentStatus::Draft, 'Draft Doc'],
    'in review' => [DocumentStatus::InReview, 'In Review Doc'],
    'returned' => [DocumentStatus::Returned, 'Returned Doc'],
    'approved' => [DocumentStatus::Approved, 'Approved Doc'],
    'rejected' => [DocumentStatus::Rejected, 'Rejected Doc'],
]);

test('an unknown form_type or status filter value is ignored rather than emptying the page', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Still Here', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index', ['form_type' => 'not_a_real_type', 'status' => 'also_bogus']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)->etc());
});

test('search matches the document title', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Findable Title', $this->president);
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Something Else', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'Findable Title')
            ->etc()
        );
});

test('results are paginated at 20 per page, most recent activity first', function () {
    foreach (range(1, 25) as $i) {
        historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, "Doc {$i}", $this->president)
            // Distinct, increasing updated_at so ordering is deterministic
            // rather than relying on same-second factory timestamps.
            ->forceFill(['updated_at' => now()->addSeconds($i)])->save();
    }

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 20)
            ->where('documents.meta.current_page', 1)
            ->where('documents.meta.last_page', 2)
            ->where('documents.meta.total', 25)
            ->where('documents.data.0.title', 'Doc 25')
            ->etc()
        );

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 5)
            ->where('documents.meta.total', 25)
            ->etc()
        );
});

test('the last-activity column reflects the transition log, not just the document row\'s own timestamp', function () {
    // Distinct, well-separated timestamps — same precedent as the pagination
    // test below — so ordering is deterministic rather than depending on
    // both rows landing in the same second and falling back to the
    // orderByDesc('id') tiebreak.
    $untouchedDraft = historyDocument(FormType::ActivityProposal, $this->computingSociety, DocumentStatus::Draft, 'Untouched Draft', $this->president);
    $untouchedDraft->forceFill(['created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)])->save();

    $submitted = historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Draft, 'About To Be Submitted', $this->president);
    $submitted->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
    $this->engine->submit($submitted, $this->president);
    $submitted->refresh();
    expect($submitted->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 2)
            ->where('documents.data.0.id', $submitted->id)
            ->where(
                'documents.data.0.lastActivityAt',
                fn ($value) => $submitted->latestTransition->created_at->equalTo($value),
            )
            ->where('documents.data.1.id', $untouchedDraft->id)
            // A zero-transition Draft falls back to its own created_at.
            ->where(
                'documents.data.1.lastActivityAt',
                fn ($value) => $untouchedDraft->created_at->equalTo($value),
            )
            ->etc()
        );
});

test('a student with no active membership gets an empty list and a 200, not a 403', function () {
    historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Not Theirs', $this->president);

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('document-history/index')
            ->has('documents.data', 0)
            ->where('stats.total', 0)
            ->etc()
        );
});

test('each form type\'s row links to its own student-facing show route', function (FormType $formType, string $routeName) {
    $doc = historyDocument($formType, $this->computingSociety, DocumentStatus::Approved, 'Linked Document', $this->president);

    $this->actingAs($this->president)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.href', route($routeName, $doc))
            ->etc()
        );
})->with([
    'registration' => [FormType::OrganizationRegistration, 'registrations.show'],
    'renewal' => [FormType::OrganizationRenewal, 'renewals.show'],
    'activity calendar' => [FormType::ActivityCalendar, 'activity-calendars.show'],
    'activity proposal' => [FormType::ActivityProposal, 'activity-proposals.show'],
    'after-activity report' => [FormType::AfterActivityReport, 'reports.show'],
]);

test('a row opens for its own officer, while another organization\'s document still 403s on a direct URL', function () {
    $own = historyDocument(FormType::OrganizationRegistration, $this->itGuild, DocumentStatus::Approved, 'IT Guild Registration', $this->otherOrgOfficer);
    $foreign = historyDocument(FormType::OrganizationRegistration, $this->computingSociety, DocumentStatus::Approved, 'Computing Society Registration', $this->president);

    $this->actingAs($this->otherOrgOfficer)->withoutVite()
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.href', route('registrations.show', $own))
            ->etc()
        );

    $this->actingAs($this->otherOrgOfficer)->withoutVite()
        ->get(route('registrations.show', $own))->assertOk();

    // DocumentPolicy::view()'s existing boundary, unchanged by this feature.
    $this->actingAs($this->otherOrgOfficer)->withoutVite()
        ->get(route('registrations.show', $foreign))->assertForbidden();
});
