<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
});

function approverIndex(User $actor)
{
    return test()->actingAs($actor)->get(route('admin.approvers.index'));
}

test('the active role counts match the active role holders, and exclude deactivated accounts', function () {
    $expected = fn (array $roles) => User::query()->active()
        ->whereHas('roleAssignments', fn ($q) => $q->whereIn('role', array_map(fn (Role $r) => $r->value, $roles)))
        ->count();

    approverIndex($this->sdaoA)->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/approvers/index')
        ->where('stats.active.byGroup.adviser', $expected([Role::Adviser]))
        ->where('stats.active.byGroup.sdao', $expected([Role::SdaoMember]))
        ->where('stats.active.byGroup.dean', $expected([Role::Dean, Role::Principal]))
        ->where('stats.active.byGroup.program_chair', $expected([Role::ProgramChair]))
        ->where('stats.active.byGroup.director', $expected([Role::AssistantDirectorAcademicServices, Role::AcademicDirector, Role::ExecutiveDirector]))
    );

    $dean = User::query()->active()->whereHas('roleAssignments', fn ($q) => $q->where('role', Role::Dean->value))->firstOrFail();
    $before = approverIndex($this->sdaoA)->viewData('page')['props']['stats']['active']['total'];
    $dean->forceFill(['deactivated_at' => now()])->save();

    approverIndex($this->sdaoA)->assertInertia(fn ($page) => $page
        ->where('stats.active.total', $before - 1)
        ->where('stats.deactivated.count', 1)
    );
});

test('the missing adviser count is the dashboard rule: approved organizations without an adviser', function () {
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Approved,
    ]);
    RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->delete();

    approverIndex($this->sdaoA)->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.missingAdviser.count', 1)
        ->where('stats.missingAdviser.organizations.0.name', $this->org->name)
        ->where('stats.missingAdviser.href', route('admin.organizations.index', ['adviser' => 'none']))
    );
});

test('no organization is missing an adviser when none is approved without one', function () {
    approverIndex($this->sdaoA)->assertInertia(fn ($page) => $page->where('stats.missingAdviser.count', 0));
});

test('the deactivated card carries the count and the most recently deactivated account', function () {
    $older = User::factory()->create(['name' => 'Older Account', 'deactivated_at' => now()->subDays(5)]);
    $newer = User::factory()->create(['name' => 'Newer Account', 'deactivated_at' => now()->subDay()]);

    approverIndex($this->sdaoA)->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.deactivated.count', 2)
        ->where('stats.deactivated.latest.name', $newer->name)
        ->has('stats.deactivated.latest.at')
    );

    expect($older->exists)->toBeTrue();
});

test('rows carry their group, role label and what they approve for', function () {
    $page = approverIndex($this->sdaoA)->viewData('page')['props']['approvers'];

    $sdao = collect($page)->firstWhere('email', $this->sdaoA->email);
    expect($sdao['group'])->toBe('sdao')
        ->and($sdao['role_label'])->toBe('SDAO Member')
        ->and($sdao['approves_for']['primary'])->toBe('Whole school')
        ->and($sdao['scope_key'])->toBe('global');

    $adviserAssignment = RoleAssignment::where('role', Role::Adviser->value)->whereNotNull('organization_id')->firstOrFail();
    $adviser = collect($page)->firstWhere('id', $adviserAssignment->user_id);
    expect($adviser['group'])->toBe('adviser')
        ->and($adviser['approves_for']['primary'])->toBe($adviserAssignment->organization->name);
});

test('a non SDAO user cannot open the page', function () {
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($student)->get(route('admin.approvers.index'))->assertForbidden();
});
