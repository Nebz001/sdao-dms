<?php

use App\Approval\Notifications\MailingSubmitterNotifier;
use App\Approval\Notifications\RecordingApproverNotifier;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\Registrations\StoreRegistrationRequest;
use App\Identity\Admin\DeactivateAccount;
use App\Identity\Admin\ReactivateAccount;
use App\Identity\RoleDirectory;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\PushToken;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Passkeys;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    // A retired, role-less, non student account on a personal address.
    $this->retired = User::factory()->create([
        'name' => 'Old Test Account',
        'email' => 'old-test@gmail.com',
        'password' => 'old-password-1',
    ]);
});

function deactivateAs(User $actor, User $account, ?string $reason = null): User
{
    return app(DeactivateAccount::class)->execute($actor->fresh(), $account, $reason);
}

// ---------------------------------------------------------------------------
// The action
// ---------------------------------------------------------------------------

test('an SDAO member can deactivate a non student account, recording when, why and by whom', function () {
    deactivateAs($this->sdaoA, $this->retired, 'Replaced as SDAO member');

    $this->retired->refresh();

    expect($this->retired->isDeactivated())->toBeTrue()
        ->and($this->retired->deactivated_reason)->toBe('Replaced as SDAO member')
        ->and($this->retired->deactivated_by)->toBe($this->sdaoA->id)
        ->and(User::whereKey($this->retired->id)->exists())->toBeTrue();
});

test('deactivation ends every session, revokes tokens, deletes push tokens and rotates the remember token', function () {
    config(['session.driver' => 'database']);

    $this->retired->forceFill(['remember_token' => 'old-remember-token'])->save();
    $this->retired->createToken('phone');
    PushToken::create([
        'user_id' => $this->retired->id,
        'device_id' => 'device-1',
        'token' => 'ExponentPushToken[abc]',
        'platform' => 'ios',
        'last_seen_at' => now(),
    ]);
    DB::table('password_reset_tokens')->insert(['email' => $this->retired->email, 'token' => 'hash', 'created_at' => now()]);

    foreach (['browser-a', 'browser-b'] as $id) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $this->retired->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    }
    DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => $this->sdaoB->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    deactivateAs($this->sdaoA, $this->retired);

    expect(DB::table('sessions')->where('user_id', $this->retired->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'someone-else')->count())->toBe(1)
        ->and($this->retired->tokens()->count())->toBe(0)
        ->and(PushToken::where('user_id', $this->retired->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $this->retired->email)->count())->toBe(0)
        ->and($this->retired->refresh()->remember_token)->not->toBe('old-remember-token')
        ->and($this->retired->remember_token)->not->toBeNull();
});

test('only an SDAO member can deactivate', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => deactivateAs($adviser, $this->retired))->toThrow(AuthorizationException::class);
    expect($this->retired->refresh()->isDeactivated())->toBeFalse();
});

test('an admin cannot deactivate themselves or an already deactivated account', function () {
    expect(fn () => deactivateAs($this->sdaoA, $this->sdaoA))->toThrow(ValidationException::class);

    deactivateAs($this->sdaoA, $this->retired);

    expect(fn () => deactivateAs($this->sdaoA, $this->retired))->toThrow(ValidationException::class);
});

test('a student account can be deactivated, and its officer seat ends with it (details in StudentAccountDeactivationTest)', function () {
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    deactivateAs($this->sdaoA, $student);

    expect($student->refresh()->isDeactivated())->toBeTrue();
    expect($student->organizationMemberships()->active()->exists())->toBeFalse();
});

test('the sole holder of a seat cannot be deactivated until a replacement exists', function () {
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $director = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();
    $boundAdviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => deactivateAs($this->sdaoA, $dean))->toThrow(ValidationException::class, 'Provision a replacement first');
    expect(fn () => deactivateAs($this->sdaoA, $director))->toThrow(ValidationException::class, 'Provision a replacement first');
    expect(fn () => deactivateAs($this->sdaoA, $boundAdviser))->toThrow(ValidationException::class, 'Provision a replacement first');

    expect($dean->refresh()->isDeactivated())->toBeFalse();
});

test('an unassigned adviser in the pool can be deactivated', function () {
    $pool = User::factory()->create(['email' => 'pool-adviser@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $pool->id, 'role' => Role::Adviser]);

    deactivateAs($this->sdaoA, $pool);

    expect($pool->refresh()->isDeactivated())->toBeTrue();
});

test('an SDAO member cannot be deactivated if fewer than the two required would remain', function () {
    expect(fn () => deactivateAs($this->sdaoA, $this->sdaoB))
        ->toThrow(ValidationException::class, 'SDAO approval needs 2 active members');

    $third = User::factory()->create(['email' => 'third-sdao@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $third->id, 'role' => Role::SdaoMember]);

    deactivateAs($this->sdaoA, $this->sdaoB);

    expect($this->sdaoB->refresh()->isDeactivated())->toBeTrue();
});

test('reactivating lifts the deactivation and the account can log in again', function () {
    deactivateAs($this->sdaoA, $this->retired, 'Testing');

    app(ReactivateAccount::class)->execute($this->sdaoA->fresh(), $this->retired);

    $this->retired->refresh();

    expect($this->retired->isDeactivated())->toBeFalse()
        ->and($this->retired->deactivated_reason)->toBeNull()
        ->and($this->retired->deactivated_by)->toBeNull();

    $this->post(route('login.store'), ['email' => $this->retired->email, 'password' => 'old-password-1']);
    $this->assertAuthenticatedAs($this->retired);
});

test('the deactivate and reactivate endpoints flash a confirmation', function () {
    $this->actingAs($this->sdaoA)
        ->post(route('admin.accounts.deactivate', $this->retired), ['reason' => 'Test'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash.title', fn (string $m) => $m === 'Account deactivated');

    $this->actingAs($this->sdaoA)
        ->post(route('admin.accounts.reactivate', $this->retired))
        ->assertSessionHas('flash.title', fn (string $m) => $m === 'Account reactivated');
});

test('the account endpoints are admin only', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $this->actingAs($adviser)->post(route('admin.accounts.deactivate', $this->retired))->assertForbidden();
    $this->actingAs($adviser)->get(route('admin.accounts.search', ['q' => 'old']))->assertForbidden();
});

// ---------------------------------------------------------------------------
// Login, reset, passkeys
// ---------------------------------------------------------------------------

test('a deactivated account cannot log in and gets a specific message', function () {
    deactivateAs($this->sdaoA, $this->retired);

    $this->post(route('login.store'), ['email' => $this->retired->email, 'password' => 'old-password-1'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact SDAO if you think this is a mistake.']);

    $this->assertGuest();
});

test('a wrong password on a deactivated account gets the ordinary password error, not the deactivation message', function () {
    deactivateAs($this->sdaoA, $this->retired);

    $this->post(route('login.store'), ['email' => $this->retired->email, 'password' => 'guess'])
        ->assertSessionHasErrors('password')
        ->assertSessionDoesntHaveErrors('email');

    $this->assertGuest();
});

test('a passkey login is denied for a deactivated account', function () {
    $passkeyClass = Passkeys::passkeyModel();
    $request = Request::create('/passkeys/login', 'POST');

    $active = (new $passkeyClass)->setRelation('user', $this->retired);
    expect(Passkeys::allowsLogin($request, $active))->toBeTrue();

    deactivateAs($this->sdaoA, $this->retired);

    $denied = (new $passkeyClass)->setRelation('user', $this->retired->fresh());
    expect(Passkeys::allowsLogin($request, $denied))->toBeFalse();
});

test('a deactivated account is sent no password reset link', function () {
    Notification::fake();
    deactivateAs($this->sdaoA, $this->retired);

    $this->post(route('password.email'), ['email' => $this->retired->email]);

    Notification::assertNotSentTo($this->retired, ResetPassword::class);
});

test('an active account still gets its reset link', function () {
    Notification::fake();

    $this->post(route('password.email'), ['email' => $this->retired->email]);

    Notification::assertSentTo($this->retired, ResetPassword::class);
});

test('a reset token issued before deactivation cannot be used to get back in', function () {
    $token = Password::createToken($this->retired);
    deactivateAs($this->sdaoA, $this->retired);

    // Deactivation purges the token, and the reset itself also refuses.
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->retired->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors();

    $this->assertGuest();
    expect(password_verify('brand-new-password', $this->retired->refresh()->password))->toBeFalse();
});

test('the reset action itself refuses a deactivated account even with a valid token', function () {
    $this->retired->forceFill(['deactivated_at' => now()])->save();
    $token = Password::createToken($this->retired);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->retired->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(password_verify('brand-new-password', $this->retired->refresh()->password))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Already logged in
// ---------------------------------------------------------------------------

test('a logged in user who is deactivated is signed out on the next request', function () {
    $this->actingAs($this->retired);
    $this->retired->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('calendar.index'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an Inertia partial reload from a deactivated user gets an Inertia location to the login page', function () {
    $this->actingAs($this->retired);
    $this->retired->forceFill(['deactivated_at' => now()])->save();

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'dashboard',
        'X-Inertia-Partial-Data' => 'notifications',
    ])
        ->get(route('dashboard'))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));

    $this->assertGuest();
});

test('a plain JSON call from a deactivated user gets a 401 with the account_deactivated code', function () {
    $this->actingAs($this->retired);
    $this->retired->forceFill(['deactivated_at' => now()])->save();

    $this->getJson(route('notifications.index'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'account_deactivated');

    $this->assertGuest();
});

test('an active user is untouched by the middleware', function () {
    $this->actingAs($this->retired)->get(route('calendar.index'))->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Mobile
// ---------------------------------------------------------------------------

test('a deactivated approver gets 403 account_deactivated on mobile login, ahead of the password change code', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['deactivated_at' => now(), 'must_change_password' => true])->save();

    $this->postJson('/api/mobile/login', [
        'email' => $adviser->email,
        'password' => 'ict@1234',
        'device_name' => 'Test Phone',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated')
        ->assertJsonMissingPath('token');

    expect($adviser->tokens()->count())->toBe(0);
});

test('a mobile token for a deactivated account is dead', function () {
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $token = $adviser->createToken('phone')->plainTextToken;

    $adviser->forceFill(['deactivated_at' => now()])->save();

    // Even if a token survived, the middleware answers with the same code.
    $this->withToken($token)->getJson('/api/mobile/user')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_deactivated');
});

// ---------------------------------------------------------------------------
// Not an approver, notification recipient or adviser choice
// ---------------------------------------------------------------------------

test('a deactivated SDAO member no longer resolves as an approver', function () {
    $third = User::factory()->create(['email' => 'third-sdao@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $third->id, 'role' => Role::SdaoMember]);
    deactivateAs($this->sdaoA, $this->sdaoB);

    $ids = app(RoleDirectory::class)->sdaoMembers()->pluck('id')->all();

    expect($ids)->not->toContain($this->sdaoB->id)
        ->and($ids)->toContain($this->sdaoA->id, $third->id);
});

test('a deactivated sole holder of a seat resolves like a vacant seat', function () {
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $dean->forceFill(['deactivated_at' => now()])->save();

    expect(fn () => app(RoleDirectory::class)->deanFor($org))
        ->toThrow(ModelNotFoundException::class);
});

test('a deactivated approver gets no hand off notification of any kind', function () {
    Notification::fake();

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'status' => DocumentStatus::InReview,
    ]);
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $adviser->forceFill(['deactivated_at' => now()])->save();

    app(RecordingApproverNotifier::class)->notify($adviser->fresh(), $doc, 1, TransitionAction::Submitted);

    expect(ApprovalNotification::where('user_id', $adviser->id)->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a deactivated submitter gets no outcome notification', function () {
    Notification::fake();

    $doc = Document::factory()->create(['status' => DocumentStatus::Approved]);
    $this->retired->forceFill(['deactivated_at' => now()])->save();

    app(MailingSubmitterNotifier::class)->notify($this->retired->fresh(), $doc, DocumentStatus::Approved);

    Notification::assertNothingSent();
});

test('a deactivated adviser is not offered in the adviser search', function () {
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $pool = User::factory()->create(['name' => 'Pool Adviser Zed', 'email' => 'pool-zed@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $pool->id, 'role' => Role::Adviser]);

    $this->actingAs(User::factory()->create(['account_status' => AccountStatus::Verified, 'email' => 'fresh-student@students.nu-lipa.edu.ph']))
        ->getJson(route('registrations.adviser-search', ['q' => 'Zed']))
        ->assertOk()
        ->assertJsonCount(1, 'advisers');

    $pool->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs(User::where('email', 'fresh-student@students.nu-lipa.edu.ph')->firstOrFail())
        ->getJson(route('registrations.adviser-search', ['q' => 'Zed']))
        ->assertOk()
        ->assertJsonCount(0, 'advisers');

    expect($student)->not->toBeNull();
});

test('registration validation rejects a deactivated adviser id', function () {
    $pool = User::factory()->create(['email' => 'pool-zed@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $pool->id, 'role' => Role::Adviser]);

    $rules = ['adviser_id' => (new StoreRegistrationRequest)->rules()['adviser_id']];

    expect(Validator::make(['adviser_id' => $pool->id], $rules)->passes())->toBeTrue();

    $pool->forceFill(['deactivated_at' => now()])->save();

    expect(Validator::make(['adviser_id' => $pool->id], $rules)->passes())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Admin screens
// ---------------------------------------------------------------------------

test('the approvers list keeps a deactivated, role-less account visible with who deactivated it', function () {
    deactivateAs($this->sdaoA, $this->retired, 'Replaced');

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.approvers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/approvers/index')
            ->where('approvers', fn ($approvers) => collect($approvers)->contains(fn ($a) => $a['email'] === 'old-test@gmail.com'
                && $a['deactivated_by'] === $this->sdaoA->name
                && $a['deactivated_reason'] === 'Replaced'
                && $a['roles'] === [])),
        );
});

test('the account search finds non student accounts, including role-less ones, and never students', function () {
    $response = $this->actingAs($this->sdaoA)->getJson(route('admin.accounts.search', ['q' => 'old-test']))->assertOk();
    expect(collect($response->json('accounts'))->pluck('email')->all())->toBe(['old-test@gmail.com']);

    $students = $this->actingAs($this->sdaoA)->getJson(route('admin.accounts.search', ['q' => 'student-alpha']))->assertOk();
    expect($students->json('accounts'))->toBe([]);
});

test('the account search needs two characters and is capped', function () {
    $this->actingAs($this->sdaoA)->getJson(route('admin.accounts.search', ['q' => 'o']))->assertOk()->assertJson(['accounts' => []]);

    foreach (range(1, 12) as $i) {
        User::factory()->create(['name' => "Bulk Match {$i}", 'email' => "bulk{$i}@gmail.com"]);
    }

    $this->actingAs($this->sdaoA)->getJson(route('admin.accounts.search', ['q' => 'Bulk Match']))
        ->assertOk()
        ->assertJsonCount(8, 'accounts');
});

test('the account search carries the same throttle as the adviser search', function () {
    $search = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->getName() === 'admin.accounts.search');
    $adviser = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->getName() === 'registrations.adviser-search');

    expect($search->gatherMiddleware())->toContain('throttle:30,1')
        ->and($adviser->gatherMiddleware())->toContain('throttle:30,1');
});

test('student accounts are identified by student domain, membership or student role', function () {
    $alpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $withRole = User::factory()->create(['email' => 'odd-student@gmail.com']);
    RoleAssignment::create(['user_id' => $withRole->id, 'role' => Role::Student]);

    expect($alpha->isStudentAccount())->toBeTrue()
        ->and($withRole->isStudentAccount())->toBeTrue()
        ->and($this->retired->isStudentAccount())->toBeFalse()
        ->and($this->sdaoA->isStudentAccount())->toBeFalse();
});

// ---------------------------------------------------------------------------
// History
// ---------------------------------------------------------------------------

test('history keeps showing the real name of a deactivated actor', function () {
    $doc = Document::factory()->create(['form_type' => FormType::ActivityProposal, 'status' => DocumentStatus::InReview]);
    DocumentTransition::factory()->create([
        'document_id' => $doc->id,
        'actor_id' => $this->retired->id,
        'action' => TransitionAction::Approved,
        'created_at' => now(),
    ]);

    deactivateAs($this->sdaoA, $this->retired);

    expect(DocumentTransition::where('actor_id', $this->retired->id)->count())->toBe(1)
        ->and(DocumentTransition::first()->actor->name)->toBe('Old Test Account');

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.activity.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('transitions.data', fn ($rows) => collect($rows)->contains(fn ($r) => $r['actorName'] === 'Old Test Account')));
});
