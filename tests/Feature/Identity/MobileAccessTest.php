<?php

use App\Enums\Role;
use App\Identity\MobileAccess;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);

    $this->mobileAccess = app(MobileAccess::class);
});

test('approverRoles() returns exactly the roles used by activity proposal chains, configuration-derived', function () {
    $roles = $this->mobileAccess->approverRoles()->map(fn (Role $r) => $r->value)->sort()->values()->all();

    expect($roles)->toEqualCanonicalizing([
        'adviser',
        'program_chair',
        'dean',
        'principal',
        'sdao_member',
        'assistant_director_academic_services',
        'academic_director',
        'executive_director',
    ]);

    // Student is never a workflow step role for any form type.
    expect($roles)->not->toContain('student');
});

test('a verified adviser can access the mobile app', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect($this->mobileAccess->canAccess($adviser))->toBeTrue();
    expect($this->mobileAccess->approverRolesFor($adviser)->map(fn (Role $r) => $r->value)->all())
        ->toBe(['adviser']);
});

test('a verified SDAO member can access the mobile app', function () {
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    expect($this->mobileAccess->canAccess($sdao))->toBeTrue();
});

test('a student is never granted mobile access, even if verified', function () {
    $student = User::factory()->create([
        'email' => 'student-x@students.nu-lipa.edu.ph',
    ]);
    $student->roleAssignments()->create(['role' => Role::Student]);

    expect($this->mobileAccess->canAccess($student))->toBeFalse();
    expect($this->mobileAccess->approverRolesFor($student))->toBeEmpty();
});

test('an approver with an unverified email cannot access the mobile app', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['email_verified_at' => null])->save();

    expect($this->mobileAccess->canAccess($adviser))->toBeFalse();
});

test('an approver whose account_status is not Verified cannot access the mobile app', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['account_status' => 'unverified'])->save();

    expect($this->mobileAccess->canAccess($adviser))->toBeFalse();
});

test('a Rejected approver account cannot access the mobile app', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['account_status' => 'rejected'])->save();

    expect($this->mobileAccess->canAccess($adviser))->toBeFalse();
});

test('a user with no role assignments at all cannot access the mobile app', function () {
    $bareUser = User::factory()->create();

    expect($this->mobileAccess->canAccess($bareUser))->toBeFalse();
});
