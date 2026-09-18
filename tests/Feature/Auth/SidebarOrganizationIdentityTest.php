<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The sidebar's org-branding swap (app-sidebar.tsx) and footer school line
 * (nav-user.tsx) both key off the shared `auth.organization` Inertia prop —
 * see HandleInertiaRequests::authProp()/organizationProp(). This covers
 * every state that prop can be in, and the accompanying OrganizationLogoController
 * route.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('a president of a school-bound org gets auth.organization with id, name, and school', function () {
    $president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // Computing Society
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $response = $this->actingAs($president)->withoutVite()->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization.id', $org->id)
        ->where('auth.organization.name', 'Computing Society')
        ->where('auth.organization.school.name', 'School of Computing and IT')
        ->where('auth.isActiveOfficer', true)
    );
});

test('a secretary of the same org gets the identical auth.organization shape — this is not president-only', function () {
    $secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $response = $this->actingAs($secretary)->withoutVite()->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization.id', $org->id)
        ->where('auth.organization.name', 'Computing Society')
        ->where('auth.organization.school.name', 'School of Computing and IT')
        ->where('auth.isActiveOfficer', true)
    );
});

test('a president of an Extra-Curricular org with no college gets a null school — the footer omits its second line', function () {
    $president = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail(); // University Chess Club

    $response = $this->actingAs($president)->withoutVite()->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization.name', 'University Chess Club')
        ->where('auth.organization.school', null)
        ->where('auth.isActiveOfficer', true)
    );
});

test('an org with no logo has a null logoUrl', function () {
    $president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $response = $this->actingAs($president)->withoutVite()->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization.logoUrl', null)
    );
});

test('an org with a logo path gets a resolvable organizations.logo URL', function () {
    $president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $org->update(['logo_path' => 'organizations/1/logo.png', 'logo_disk' => 'local']);

    $response = $this->actingAs($president)->withoutVite()->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization.logoUrl', route('organizations.logo', $org))
    );
});

test('SDAO staff, advisers, and an unaffiliated student all get a null auth.organization', function () {
    // sdao-a redirects dashboard -> admin.dashboard.index (DashboardController)
    // — a distinct role from the other two, so it's asserted separately
    // against its own real landing page rather than forcing all three
    // through the same route.
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $unaffiliated = User::factory()->create();

    $response = $this->actingAs($sdao)->withoutVite()->get(route('admin.dashboard.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.organization', null)
        ->where('auth.isActiveOfficer', false)
    );

    foreach ([$adviser, $unaffiliated] as $user) {
        $response = $this->actingAs($user)->withoutVite()->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.organization', null)
            ->where('auth.isActiveOfficer', false)
        );
    }
});

test('the logo route 404s when the org has no logo', function () {
    $president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();

    $this->actingAs($president)->get(route('organizations.logo', $org))->assertNotFound();
});

test('the logo route streams the file when a logo is set', function () {
    Storage::fake('local');
    // ->create(), not ->image() — the latter requires the GD extension to
    // render a real image, which isn't guaranteed available in every test
    // environment; a plain fake binary file exercises the same
    // Storage::disk()->response() streaming path without that dependency.
    Storage::disk('local')->put('organizations/1/logo.png', UploadedFile::fake()->create('logo.png', 5)->getContent());

    $president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $org->update(['logo_path' => 'organizations/1/logo.png', 'logo_disk' => 'local']);

    $this->actingAs($president)->get(route('organizations.logo', $org))->assertOk();
});
