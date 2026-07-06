<?php

use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(BindOrganizationOfficer::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
});

test('adviser can bind a student as president', function () {
    $newStudent = User::factory()->create();
    RoleAssignment::create(['user_id' => $newStudent->id, 'role' => Role::Student, 'organization_id' => $this->org->id]);

    $membership = $this->action->execute(
        actor: $this->adviser,
        organization: $this->org,
        student: $newStudent,
        position: OfficerPosition::President,
    );

    expect($membership->is_active)->toBeTrue();
    expect($membership->position)->toBe(OfficerPosition::President);
    expect($membership->user_id)->toBe($newStudent->id);
});

test('binding a second president deactivates the first and retains the old record', function () {
    $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $oldMembership = OrganizationMembership::where('user_id', $studentAlpha->id)
        ->where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::President->value)
        ->where('is_active', true)
        ->firstOrFail();

    $newStudent = User::factory()->create();
    RoleAssignment::create(['user_id' => $newStudent->id, 'role' => Role::Student, 'organization_id' => $this->org->id]);

    $this->action->execute(
        actor: $this->adviser,
        organization: $this->org,
        student: $newStudent,
        position: OfficerPosition::President,
    );

    // Old membership deactivated but retained.
    $oldMembership->refresh();
    expect($oldMembership->is_active)->toBeFalse();

    // Old record still exists (not hard-deleted).
    expect(OrganizationMembership::find($oldMembership->id))->not->toBeNull();

    // Exactly one active president.
    $activePresidents = OrganizationMembership::query()
        ->where('organization_id', $this->org->id)
        ->where('position', OfficerPosition::President->value)
        ->where('is_active', true)
        ->count();
    expect($activePresidents)->toBe(1);
});

test('a non-adviser cannot bind officers', function () {
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    expect(fn () => $this->action->execute(
        actor: $sdaoA,
        organization: $this->org,
        student: $student,
        position: OfficerPosition::President,
    ))->toThrow(AuthorizationException::class);
});

test('officer can be bound for secretary position independently of president', function () {
    $newStudent = User::factory()->create();
    RoleAssignment::create(['user_id' => $newStudent->id, 'role' => Role::Student, 'organization_id' => $this->org->id]);

    $membership = $this->action->execute(
        actor: $this->adviser,
        organization: $this->org,
        student: $newStudent,
        position: OfficerPosition::Secretary,
    );

    // Both president and secretary active simultaneously.
    $activeMemberships = OrganizationMembership::where('organization_id', $this->org->id)
        ->where('is_active', true)
        ->count();
    expect($activeMemberships)->toBeGreaterThanOrEqual(2);
    expect($membership->position)->toBe(OfficerPosition::Secretary);
});
