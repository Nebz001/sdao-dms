<?php

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * The admin organizations index sits behind the same `can:access-admin` gate
 * as the rest of admin/* (AppServiceProvider::configureGates(), Role::SdaoMember
 * only) — mirrors DocumentArchiveAuthorizationTest's cast of non-SDAO roles.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
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

test('the stats strip reflects the search filter but not the status filter', function () {
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.organizations.index', ['status' => OrganizationStatus::Active->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', Organization::query()->count())
        );
});
