<?php

use App\Enums\Role;
use App\Models\PrivacyNoticeAcceptance;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\PrivacyNotice;
use Inertia\Inertia;

beforeEach(function () {
    // Every other test starts as someone who already accepted; these do not.
    $this->withoutPrivacyNoticeAcceptance();
});

function userWithRole(?Role $role): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        RoleAssignment::factory()->create(['user_id' => $user->id, 'role' => $role]);
    }

    return $user;
}

test('a successful login lands on the privacy notice, not the dashboard', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('privacy-notice.show'));

    $this->assertAuthenticated();
    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));
});

test('the privacy notice page renders for a user who has not accepted', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('privacy-notice.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('privacy-notice')->where('version', PrivacyNotice::VERSION));
});

test('every protected page is blocked until the notice is accepted', function (string $routeName) {
    $this->actingAs(userWithRole(Role::SdaoMember));

    $this->get(route($routeName))->assertRedirect(route('privacy-notice.show'));
})->with([
    'dashboard',
    'hubs.submit',
    'hubs.my-documents',
    'track.index',
    'notifications.index',
    'document-history.index',
    'organizations.mine',
    'profile.edit',
    'appearance.edit',
    'admin.dashboard.index',
]);

test('a direct url, an inertia visit and a partial reload are all blocked', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')->assertRedirect(route('privacy-notice.show'));

    $this->get('/dashboard', ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])
        ->assertRedirect(route('privacy-notice.show'));

    $this->get('/dashboard', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'dashboard',
        'X-Inertia-Partial-Data' => 'auth',
    ])->assertRedirect(route('privacy-notice.show'));
});

test('a blocked write is refused too', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('attachments.store'))->assertRedirect(route('privacy-notice.show'));
    $this->patch(route('notifications.read-all'))->assertRedirect(route('privacy-notice.show'));
});

test('a background fetch gets a 403 json instead of a redirect', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson('/notifications')
        ->assertForbidden()
        ->assertJson(['code' => 'privacy_notice_required']);
});

test('accepting without the checkbox is rejected by the server', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('privacy-notice.show'))
        ->post(route('privacy-notice.accept'), [])
        ->assertSessionHasErrors('agree');

    $this->actingAs($user)->post(route('privacy-notice.accept'), ['agree' => false])
        ->assertSessionHasErrors('agree');

    expect(PrivacyNoticeAcceptance::count())->toBe(0);
    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));
});

test('accepting records user, time, ip and version, and opens the system', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post(route('privacy-notice.accept'), ['agree' => true])
        ->assertRedirect(route('dashboard'));

    $row = PrivacyNoticeAcceptance::sole();
    expect($row->user_id)->toBe($user->id)
        ->and($row->notice_version)->toBe(PrivacyNotice::VERSION)
        ->and($row->ip_address)->toBe('203.0.113.9')
        ->and($row->accepted_at)->not->toBeNull();

    $this->get(route('dashboard'))->assertOk();
});

test('accepting returns the user to the page they were headed to', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/profile')->assertRedirect(route('privacy-notice.show'));

    $this->post(route('privacy-notice.accept'), ['agree' => true])
        ->assertRedirect(url('/settings/profile'));
});

test('an intended url on another host is ignored', function () {
    $this->actingAs(User::factory()->create())
        ->withSession([PrivacyNotice::INTENDED_KEY => 'https://evil.example/phish'])
        ->post(route('privacy-notice.accept'), ['agree' => true])
        ->assertRedirect(route('dashboard'));
});

test('the notice page sends an already accepted user on to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->withSession([PrivacyNotice::SESSION_KEY => PrivacyNotice::VERSION])
        ->get(route('privacy-notice.show'))
        ->assertRedirect(route('dashboard'));
});

test('logging in again asks again, and every acceptance is a new row', function () {
    $user = User::factory()->create();
    $credentials = ['email' => $user->email, 'password' => 'password'];

    $this->post(route('login.store'), $credentials);
    $this->post(route('privacy-notice.accept'), ['agree' => true]);
    $this->get(route('dashboard'))->assertOk();

    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login.store'), $credentials)->assertRedirect(route('privacy-notice.show'));
    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));

    $this->post(route('privacy-notice.accept'), ['agree' => true]);

    expect(PrivacyNoticeAcceptance::where('user_id', $user->id)->count())->toBe(2);
});

test('a fresh session for an accepted user asks again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('privacy-notice.accept'), ['agree' => true]);
    $this->get(route('dashboard'))->assertOk();

    // Session expiry and the idle timeout both end with a new, empty session.
    $this->flushSession();

    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));
});

test('declining logs the user out and returns to the login page', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('privacy-notice.decline'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(PrivacyNoticeAcceptance::count())->toBe(0);
});

test('logout is allowed before accepting', function () {
    $this->actingAs(User::factory()->create())->post(route('logout'));

    $this->assertGuest();
});

test('a new account must accept after registering', function () {
    [$user] = registerViaHttp();

    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));

    $this->post(route('privacy-notice.accept'), ['agree' => true]);
    expect(PrivacyNoticeAcceptance::where('user_id', $user->id)->count())->toBe(1);
});

test('a user who must change a temporary password can still accept first', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->get(route('privacy-notice.show'))->assertOk();
    $this->post(route('privacy-notice.accept'), ['agree' => true])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertRedirect(route('password.change.edit'));
});

test('the gate covers every role', function (?Role $role) {
    $user = userWithRole($role);
    $credentials = ['email' => $user->email, 'password' => 'password'];

    $this->post(route('login.store'), $credentials)->assertRedirect(route('privacy-notice.show'));
    $this->get(route('dashboard'))->assertRedirect(route('privacy-notice.show'));

    $this->post(route('privacy-notice.accept'), ['agree' => true])->assertRedirect();
    expect((string) $this->get(route('dashboard'))->headers->get('Location'))->not->toContain('privacy-notice');

    expect(PrivacyNoticeAcceptance::where('user_id', $user->id)->count())->toBe(1);
})->with([
    'unaffiliated student' => [null],
    ...array_combine(
        array_map(fn (Role $r) => $r->value, Role::cases()),
        array_map(fn (Role $r) => [$r], Role::cases()),
    ),
]);
