<?php

use App\Approval\ApprovalEngine;
use App\Enums\FormType;
use App\Enums\OrganizationStatus;
use App\Enums\Role;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The admin organizations index sits behind the same `can:access-admin` gate
 * as the rest of admin/* (AppServiceProvider::configureGates(), Role::SdaoMember
 * only) — mirrors DocumentArchiveAuthorizationTest's cast of non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->deanCcit = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
});

test('an SDAO member can open the organizations index', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index'))
        ->assertOk();
});

test('a student officer, an adviser, a dean, and a bare account all get 403 on the organizations index', function () {
    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.organizations.index'))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.organizations.index'))->assertForbidden();
    $this->actingAs($this->deanCcit)->withoutVite()->get(route('admin.organizations.index'))->assertForbidden();

    $bareUser = User::factory()->create();
    $this->actingAs($bareUser)->withoutVite()->get(route('admin.organizations.index'))->assertForbidden();
});

test('the index lists every seeded organization with a status and pagination meta', function () {
    $total = Organization::query()->count();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/organizations/index')
            ->has('organizations.data', min($total, 20))
            ->where('organizations.meta.total', $total)
            ->has('organizations.data.0.status')
            ->has('organizations.data.0.name')
        );
});

test('an unrecognized status filter is ignored rather than emptying the page', function () {
    $total = Organization::query()->count();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['status' => 'not-a-real-status']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.meta.total', $total)
            ->where('filters.status', null)
        );
});

test('the status filter narrows the list to matching organizations only', function () {
    // Nothing in this seed has ever been approved (MembershipSeeder wires
    // memberships directly, not through the real registration flow), so
    // every organization resolves Inactive.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['status' => OrganizationStatus::Inactive->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.meta.total', Organization::query()->count())
        );

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['status' => OrganizationStatus::Active->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.meta.total', 0)
        );
});

test('the name search filter narrows the list', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['search' => 'Computing Society']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Computing Society')
        );
});

test('the stat cards cover every organization, whatever the status or search filter', function () {
    $total = Organization::query()->count();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['status' => OrganizationStatus::Active->value, 'search' => 'Computing Society']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizations.meta.total', 0)
            ->loadDeferredProps('org-stats', fn ($reload) => $reload
                ->where('stats.total', $total)
                ->where('stats.inactive', $total)
                ->where('stats.missingRequirements', $total)
                ->has('stats.furthestBehind.name')
                ->where('stats.oldestPending', null)
                ->where('stats.renewalDue', 0)
                ->has('stats.renewalWindow.nextOpens')
            )
        );
});

test('oldest pending review is the longest-waiting in-review registration, with its review link', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $org, app(ApprovalEngine::class), $student);
    DocumentTransition::where('document_id', $doc->id)->update(['created_at' => now()->subDays(9)]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('org-stats', fn ($reload) => $reload
                ->where('stats.pendingReview', 1)
                ->where('stats.oldestPending.id', $doc->id)
                ->where('stats.oldestPending.organization.name', 'Computing Society')
                ->where('stats.oldestPending.waiting_days', 9)
                ->where('stats.oldestPending.tier', 'overdue')
                ->where('stats.oldestPending.noun', 'registration')
                ->where('stats.oldestPending.href', route('review.registrations.show', $doc))
            )
        );
});

test('the organization detail page is SDAO-only and renders a not-found state on an unknown organization', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $this->actingAs($this->studentAlpha)->withoutVite()->get(route('admin.organizations.show', $org))->assertForbidden();
    $this->actingAs($this->adviserOne)->withoutVite()->get(route('admin.organizations.show', $org))->assertForbidden();

    foreach (['999999', 'not-a-number'] as $id) {
        $this->actingAs($this->sdaoA)->withoutVite()
            ->get('/admin/organizations/'.$id)
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('admin/organizations/show')->where('organization', null));
    }
});

test('the organization detail page fills its deferred sections from existing data', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $org, app(ApprovalEngine::class), $this->studentAlpha);
    DocumentTransition::where('document_id', $doc->id)->update(['created_at' => now()->subDays(3)]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.show', $org))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/organizations/show')
            ->where('organization.name', 'Computing Society')
            ->loadDeferredProps('org-summary', fn ($reload) => $reload
                ->where('summary.status', 'pending_review')
                ->where('summary.banner.type', 'pending')
                ->where('summary.banner.waitingDays', 3)
                ->where('summary.banner.href', route('review.registrations.show', $doc))
                ->has('summary.tiles.requirementsTotal')
            )
            ->loadDeferredProps('org-requirements', fn ($reload) => $reload
                ->has('requirements', 5)
                ->where('requirements.0.key', 'registration_approved')
                ->where('requirements.0.met', false)
                ->where('requirements.0.document', null)
            )
            ->loadDeferredProps('org-documents', fn ($reload) => $reload
                ->has('documents.rows', 1)
                ->where('documents.rows.0.id', $doc->id)
                ->where('documents.rows.0.status', 'in_review')
                ->has('documents.periods', 1)
            )
            ->loadDeferredProps('org-officers', fn ($reload) => $reload
                ->has('officers')
            )
        );
});

test('the registration requirement row names the submitter and the academic year it covers', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $org, app(ApprovalEngine::class), $this->studentAlpha);
    $doc->update(['status' => 'approved', 'submitted_by' => $this->studentAlpha->id]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id, 'covers_academic_year' => '2026-2027']);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.show', $org))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('org-requirements', fn ($reload) => $reload
                ->where('requirements.0.key', 'registration_approved')
                ->where('requirements.0.detail', $this->studentAlpha->name)
                ->where('requirements.0.detailNote', '2026-2027')
            )
        );
});

test('the adviser bound requirement row carries the date the adviser was bound', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $adviser = $org->adviser()->first() ?? RoleAssignment::query()->where('role', Role::Adviser)->firstOrFail();
    $adviser->update(['organization_id' => $org->id]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.show', $org))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('org-requirements', fn ($reload) => $reload
                ->where('requirements.1.key', 'adviser_bound')
                ->whereNot('requirements.1.date', null)
            )
        );
});
