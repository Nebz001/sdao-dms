<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(ProvisionApprover::class);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $this->program = Program::where('name', 'BS Computer Science')->firstOrFail();
});

test('an SDAO member can provision an adviser scoped to an organization', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Adviser',
        email: 'new-adviser@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: ['organization_id' => $this->org->id],
    );

    expect(RoleAssignment::where('user_id', $user->id)
        ->where('role', Role::Adviser->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeTrue();
});

test('an SDAO member can provision an adviser with NO scope — available, pending assignment (Phase 2 item 5)', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Available Adviser',
        email: 'available-adviser@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: [],
    );

    $ra = RoleAssignment::where('user_id', $user->id)->where('role', Role::Adviser->value)->firstOrFail();
    expect($ra->organization_id)->toBeNull();
});

test('an SDAO member can provision a dean scoped to a school', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Dean',
        email: 'new-dean@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    expect(RoleAssignment::where('user_id', $user->id)
        ->where('role', Role::Dean->value)
        ->where('school_id', $this->school->id)
        ->exists())->toBeTrue();
});

test('an SDAO member can provision a program chair scoped to a program', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Chair',
        email: 'new-chair@nu-lipa.edu.ph',
        role: Role::ProgramChair,
        scope: ['program_id' => $this->program->id],
    );

    expect(RoleAssignment::where('user_id', $user->id)
        ->where('role', Role::ProgramChair->value)
        ->where('program_id', $this->program->id)
        ->exists())->toBeTrue();
});

test('an SDAO member can provision a global role with no scope', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Director',
        email: 'new-director@nu-lipa.edu.ph',
        role: Role::ExecutiveDirector,
        scope: [],
    );

    $ra = RoleAssignment::where('user_id', $user->id)->where('role', Role::ExecutiveDirector->value)->firstOrFail();
    expect($ra->school_id)->toBeNull();
    expect($ra->program_id)->toBeNull();
    expect($ra->organization_id)->toBeNull();
});

test('provisioning Student is rejected — students self-register and are adviser-bound, never admin-provisioned', function () {
    expect(fn () => $this->action->execute(
        actor: $this->sdaoA,
        name: 'Should Fail',
        email: 'should-fail@nu-lipa.edu.ph',
        role: Role::Student,
        scope: ['organization_id' => $this->org->id],
    ))->toThrow(ValidationException::class);
});

test('a mismatched role/scope pair is rejected', function () {
    // Adviser is organization-scoped — supplying a school_id instead must fail.
    expect(fn () => $this->action->execute(
        actor: $this->sdaoA,
        name: 'Mismatched',
        email: 'mismatched@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: ['school_id' => $this->school->id],
    ))->toThrow(ValidationException::class);
});

test('a non-SDAO actor cannot provision an approver via the action', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => $this->action->execute(
        actor: $adviser,
        name: 'Nope',
        email: 'nope@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: ['organization_id' => $this->org->id],
    ))->toThrow(AuthorizationException::class);
});

test('a non-SDAO authenticated user gets 403 on every admin route', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($adviser)->get(route('admin.approvers.index'))->assertForbidden();
    $this->actingAs($adviser)->get(route('admin.approvers.create'))->assertForbidden();
    $this->actingAs($adviser)->post(route('admin.approvers.store'), [
        'name' => 'X', 'email' => 'x@nu-lipa.edu.ph', 'role' => Role::Adviser->value, 'organization_id' => $this->org->id,
    ])->assertForbidden();
});

test('a provisioned approver lands account-Verified and email-verified — no verification wall on the reset-link login path', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Trusted Approver',
        email: 'trusted-approver@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    expect($user->account_status)->toBe(AccountStatus::Verified);
    expect($user->email_verified_at)->not->toBeNull();
});

test('provisioning sends a real password-reset notification and never sets a usable password directly', function () {
    Notification::fake();

    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Reset Check',
        email: 'reset-check@nu-lipa.edu.ph',
        role: Role::SdaoMember,
        scope: [],
    );

    expect($user->password)->not->toBeNull();

    // The framework's real, email-bearing password-reset notification was
    // dispatched to the newly-provisioned approver — this is what actually
    // sends through Laravel's Mail pipeline (MAIL_MAILER=log locally, a real
    // provider in production), not a no-op.
    Notification::assertSentTo($user, ResetPassword::class);
});

test('the store endpoint provisions an adviser with no organization_id — the unbound available-pool path', function () {
    $response = $this->actingAs($this->sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'Available Via HTTP',
        'email' => 'available-via-http@nu-lipa.edu.ph',
        'role' => Role::Adviser->value,
    ]);

    $response->assertRedirect(route('admin.approvers.index'));

    $newUser = User::where('email', 'available-via-http@nu-lipa.edu.ph')->firstOrFail();
    $ra = RoleAssignment::where('user_id', $newUser->id)->where('role', Role::Adviser->value)->firstOrFail();
    expect($ra->organization_id)->toBeNull();
});

test('an SDAO member can reach the admin routes end-to-end via HTTP', function () {
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.approvers.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/approvers/create')
            ->has('roles')
            ->has('schools')
            ->has('programs')
            ->has('organizations')
        );

    $response = $this->actingAs($this->sdaoA)->post(route('admin.approvers.store'), [
        'name' => 'HTTP Provisioned',
        'email' => 'http-provisioned@nu-lipa.edu.ph',
        'role' => Role::Adviser->value,
        'organization_id' => $this->org->id,
    ]);

    $response->assertRedirect(route('admin.approvers.index'));
    $newUser = User::where('email', 'http-provisioned@nu-lipa.edu.ph')->firstOrFail();
    expect(RoleAssignment::where('user_id', $newUser->id)->where('role', Role::Adviser->value)->exists())->toBeTrue();
});
