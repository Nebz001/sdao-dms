<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Reported gap: once a document reaches a terminal status it "vanishes" from
 * the admin side — every review queue is deliberately InReview-only
 * (RegistrationReviewController::index() etc.), and nothing else lists a
 * document once it leaves them. DocumentArchiveController::index() is the
 * fix: a single cross-form-type list of Approved/Rejected documents, gated by
 * the existing `access-admin` gate.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
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
            ->where('documents.data.0.title', 'A Renewal')
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
            ->where('documents.data.0.title', 'Rejected Doc')
        );
});

test('search matches the document title or the organization name', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Findable Title');
    archivedDocument(FormType::OrganizationRegistration, $this->itGuild, DocumentStatus::Approved, 'Something Else');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['search' => 'Findable']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['search' => 'IT Guild']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.title', 'Something Else')
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
        $doc = archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, "Doc {$i}");
        // Force distinct, increasing updated_at so ordering is deterministic
        // rather than relying on same-second factory timestamps.
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

test('stats reflect approved and rejected counts independent of the status filter', function () {
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Approved Doc');
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Approved, 'Approved Doc 2');
    archivedDocument(FormType::OrganizationRegistration, $this->org, DocumentStatus::Rejected, 'Rejected Doc');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index', ['status' => DocumentStatus::Rejected->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.approved', 2)
            ->where('stats.rejected', 1)
            ->where('stats.total', 3)
        );
});
