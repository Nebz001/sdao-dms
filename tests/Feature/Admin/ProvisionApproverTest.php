<?php

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Notifications\ApproverProvisionedNotification;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

// Regression coverage for the "document stuck after SDAO, Assistant
// Director never sees it" bug: a single-holder global role (Assistant/
// Academic/Executive Director) must never accumulate more than one
// RoleAssignment row — RoleDirectory::resolveGlobal() can only resolve one.
// IdentitySeeder (seeded in beforeEach) already assigned a placeholder
// holder for each of these three roles, so every test below provisions a
// SECOND holder and asserts it REPLACES the first rather than creating an
// ambiguous duplicate.
test('provisioning a new Assistant Director REPLACES the existing holder rather than duplicating the role', function () {
    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Asst Director',
        email: 'new-asst-director@nu-lipa.edu.ph',
        role: Role::AssistantDirectorAcademicServices,
        scope: [],
    );

    expect(RoleAssignment::where('role', Role::AssistantDirectorAcademicServices->value)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::AssistantDirectorAcademicServices->value)->value('user_id'))
        ->toBe($newHolder->id);
});

test('provisioning a new Academic Director REPLACES the existing holder rather than duplicating the role', function () {
    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Academic Director',
        email: 'new-academic-director@nu-lipa.edu.ph',
        role: Role::AcademicDirector,
        scope: [],
    );

    expect(RoleAssignment::where('role', Role::AcademicDirector->value)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::AcademicDirector->value)->value('user_id'))
        ->toBe($newHolder->id);
});

test('provisioning a new Executive Director REPLACES the existing holder rather than duplicating the role', function () {
    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'New Executive Director',
        email: 'new-executive-director@nu-lipa.edu.ph',
        role: Role::ExecutiveDirector,
        scope: [],
    );

    expect(RoleAssignment::where('role', Role::ExecutiveDirector->value)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::ExecutiveDirector->value)->value('user_id'))
        ->toBe($newHolder->id);
});

test('provisioning a new SDAO member still ADDS a row — the multi-holder role is unaffected by the single-holder fix', function () {
    $before = RoleAssignment::where('role', Role::SdaoMember->value)->count();

    $this->action->execute(
        actor: $this->sdaoA,
        name: 'New SDAO Member',
        email: 'new-sdao-member@nu-lipa.edu.ph',
        role: Role::SdaoMember,
        scope: [],
    );

    expect(RoleAssignment::where('role', Role::SdaoMember->value)->count())->toBe($before + 1);
});

// Regression coverage for the persistent-403 bug: unlike the three global
// director roles above, a SCOPE-bound single-holder role (Dean, Program
// Chair, org-bound Adviser) previously fell into a plain create() and left
// two live rows for the same role+scope — RoleDirectory's unordered
// firstOrFail() then made "who holds this seat" undefined, so a document
// reaching that step could be notified/authorized to whichever row won,
// sometimes leaving the newly provisioned holder neither notified nor able
// to open the review page at all. IdentitySeeder (seeded in beforeEach)
// already assigned an incumbent for CCIT's Dean, BS Computer Science's
// Program Chair, and Computing Society's Adviser — every test below
// provisions a REPLACEMENT for one of those and asserts the incumbent's
// seat is retired, not merely shadowed.
test('provisioning a replacement dean RETIRES the previous holder\'s assignment for that school', function () {
    $incumbent = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();

    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Replacement Dean',
        email: 'replacement-dean@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    expect(RoleAssignment::where('role', Role::Dean->value)->where('school_id', $this->school->id)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::Dean->value)->where('school_id', $this->school->id)->value('user_id'))
        ->toBe($newHolder->id);
    expect(RoleAssignment::where('user_id', $incumbent->id)->where('role', Role::Dean->value)->exists())->toBeFalse();

    expect(app(RoleDirectory::class)->deanFor($this->org)->id)->toBe($newHolder->id);
});

test('provisioning a replacement program chair RETIRES the previous holder\'s assignment for that program', function () {
    $incumbent = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();

    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Replacement Chair',
        email: 'replacement-chair@nu-lipa.edu.ph',
        role: Role::ProgramChair,
        scope: ['program_id' => $this->program->id],
    );

    expect(RoleAssignment::where('role', Role::ProgramChair->value)->where('program_id', $this->program->id)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::ProgramChair->value)->where('program_id', $this->program->id)->value('user_id'))
        ->toBe($newHolder->id);
    expect(RoleAssignment::where('user_id', $incumbent->id)->where('role', Role::ProgramChair->value)->exists())->toBeFalse();

    expect(app(RoleDirectory::class)->programChairFor($this->org)->id)->toBe($newHolder->id);
});

test('provisioning a replacement adviser UNBINDS the previous adviser back to the available pool, rather than deleting their role', function () {
    $incumbent = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $newHolder = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Replacement Adviser',
        email: 'replacement-adviser@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: ['organization_id' => $this->org->id],
    );

    expect(RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->count())->toBe(1);
    expect(RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->value('user_id'))
        ->toBe($newHolder->id);

    // The incumbent's row still exists — freed to the pool, not deleted —
    // so StoreRegistrationRequest's exists:role_assignments,user_id
    // validation and ApproveOrganizationRegistration's user_id-only lookup
    // both keep working for them, exactly as they do for an adviser freed
    // by an organization delete (AdviserFreedOnOrganizationDeleteTest).
    $incumbentAssignment = RoleAssignment::where('user_id', $incumbent->id)->where('role', Role::Adviser->value)->firstOrFail();
    expect($incumbentAssignment->organization_id)->toBeNull();

    expect(app(RoleDirectory::class)->adviserFor($this->org)->id)->toBe($newHolder->id);

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('registrations.adviser-search', ['q' => 'Adviser One']));
    $response->assertOk();
    $advisers = $response->json('advisers');
    expect($advisers)->toHaveCount(1);
    expect($advisers[0]['id'])->toBe($incumbent->id);
    expect($advisers[0]['is_available'])->toBeTrue();
});

test('provisioning a dean for one school leaves another school\'s dean untouched', function () {
    $otherSchool = School::where('name', 'School of Business and Accountancy')->firstOrFail();
    $otherDean = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Other School Dean',
        email: 'other-school-dean@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $otherSchool->id],
    );

    $incumbentCcitDean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();

    expect(RoleAssignment::where('user_id', $incumbentCcitDean->id)->where('role', Role::Dean->value)->exists())->toBeTrue();
    expect(RoleAssignment::where('role', Role::Dean->value)->where('school_id', $otherSchool->id)->value('user_id'))
        ->toBe($otherDean->id);
});

test('provisioning an unscoped adviser never disturbs the available pool or any org-bound adviser', function () {
    $this->action->execute(actor: $this->sdaoA, name: 'Pool One', email: 'pool-one@nu-lipa.edu.ph', role: Role::Adviser, scope: []);
    $this->action->execute(actor: $this->sdaoA, name: 'Pool Two', email: 'pool-two@nu-lipa.edu.ph', role: Role::Adviser, scope: []);

    $poolCountBefore = RoleAssignment::where('role', Role::Adviser->value)->whereNull('organization_id')->count();
    $boundAdviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->action->execute(actor: $this->sdaoA, name: 'Pool Three', email: 'pool-three@nu-lipa.edu.ph', role: Role::Adviser, scope: []);

    expect(RoleAssignment::where('role', Role::Adviser->value)->whereNull('organization_id')->count())->toBe($poolCountBefore + 1);
    expect(RoleAssignment::where('user_id', $boundAdviser->id)->where('organization_id', $this->org->id)->exists())->toBeTrue();
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

test('provisioning sets the default ict@1234 password immediately — no reset-link limbo', function () {
    Notification::fake();

    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Password Check',
        email: 'password-check@nu-lipa.edu.ph',
        role: Role::SdaoMember,
        scope: [],
    );

    expect(Hash::check(ProvisionApprover::DEFAULT_PASSWORD, $user->password))->toBeTrue();
});

test('provisioning sends a real ApproverProvisionedNotification carrying the default password to the new approver', function () {
    Notification::fake();

    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Reset Check',
        email: 'reset-check@nu-lipa.edu.ph',
        role: Role::SdaoMember,
        scope: [],
    );

    Notification::assertSentTo(
        $user,
        ApproverProvisionedNotification::class,
        function (ApproverProvisionedNotification $notification, array $channels) use ($user) {
            expect($channels)->toContain('mail')
                ->and($notification->role)->toBe(Role::SdaoMember)
                ->and($notification->temporaryPassword)->toBe(ProvisionApprover::DEFAULT_PASSWORD);

            $mail = $notification->toMail($user);
            $mail->assertHasSubject('Your SDAO approver account has been created');
            $mail->assertSeeInHtml(ProvisionApprover::DEFAULT_PASSWORD, false);
            $mail->assertSeeInHtml(route('login'), false);
            $mail->assertSeeInHtml(route('security.edit'), false);

            return true;
        },
    );
});

test('the default password never leaks into the persisted in-app notification payload', function () {
    Notification::fake();

    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Bell Check',
        email: 'bell-check@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    Notification::assertSentTo(
        $user,
        ApproverProvisionedNotification::class,
        function (ApproverProvisionedNotification $notification) use ($user) {
            expect($notification->toArray($user))->not->toContain(ProvisionApprover::DEFAULT_PASSWORD);

            return true;
        },
    );
});

test('a notification dispatch failure is logged but does not prevent provisioning from succeeding', function () {
    Log::spy();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('smtp boom: 550 5.7.0 Too many emails per second'));

    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Mail Down',
        email: 'mail-down@nu-lipa.edu.ph',
        role: Role::SdaoMember,
        scope: [],
    );

    expect(Hash::check(ProvisionApprover::DEFAULT_PASSWORD, $user->password))->toBeTrue();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message) => $message === 'Approver-provisioned notification failed to dispatch')
        ->atLeast()->once();
});

test('a newly provisioned approver can really log in with the default password', function () {
    $user = $this->action->execute(
        actor: $this->sdaoA,
        name: 'Can Login',
        email: 'can-login@nu-lipa.edu.ph',
        role: Role::Dean,
        scope: ['school_id' => $this->school->id],
    );

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => ProvisionApprover::DEFAULT_PASSWORD,
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
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
