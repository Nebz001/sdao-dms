<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class]);

    $this->flagged = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->flagged->forceFill(['password' => 'temp-password-1', 'must_change_password' => true])->save();
});

dataset('protected web routes', [
    'dashboard' => ['get', 'dashboard'],
    'notification bell list' => ['get', 'notifications.index'],
    'notification read all' => ['patch', 'notifications.read-all'],
    'calendar' => ['get', 'calendar.index'],
    'profile settings' => ['get', 'profile.edit'],
    'security settings' => ['get', 'security.edit'],
    'admin approvers' => ['get', 'admin.approvers.index'],
    'proposal review queue' => ['get', 'review.activity-proposals.index'],
]);

test('a flagged user is redirected to the change page from every protected route', function (string $method, string $routeName) {
    $this->actingAs($this->flagged)
        ->{$method}(route($routeName))
        ->assertRedirect(route('password.change.edit'))
        ->assertStatus(303);
})->with('protected web routes');

test('Inertia partial reloads, such as the document pollers, are redirected too', function () {
    $this->actingAs($this->flagged)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'dashboard',
            'X-Inertia-Partial-Data' => 'notifications',
        ])
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'));
});

test('a blocked redirect leaves any pending flash message untouched', function () {
    $this->actingAs($this->flagged)
        ->withSession(['flash' => ['message' => 'Keep me']])
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.edit'))
        ->assertSessionHas('flash', ['message' => 'Keep me']);
});

test('a JSON only request from a flagged user gets 403 with the password change code', function () {
    $this->actingAs($this->flagged)
        ->getJson(route('notifications.index'))
        ->assertForbidden()
        ->assertJsonPath('code', 'password_change_required');
});

test('a flagged user can open the change page, which renders outside the settings layout', function () {
    $this->actingAs($this->flagged)
        ->withoutVite()
        ->get(route('password.change.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/change-password'));
});

test('a flagged user can still log out, which is the only server route the idle timeout calls', function () {
    $this->actingAs($this->flagged)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

test('an unflagged user is redirected from the change page to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.change.edit'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->put(route('password.change.update'), [
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

test('an unflagged user is not affected by the middleware', function () {
    $this->actingAs(User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail())
        ->get(route('calendar.index'))
        ->assertSuccessful();
});

test('changing the password clears the flag, keeps the user logged in and shows a success toast', function () {
    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('flash.title', 'Password changed');

    $this->flagged->refresh();

    expect($this->flagged->must_change_password)->toBeFalse()
        ->and(Hash::check('my-new-password', $this->flagged->password))->toBeTrue();

    $this->assertAuthenticatedAs($this->flagged);

    $this->get(route('calendar.index'))->assertSuccessful();
});

test('the current password must be correct', function () {
    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'not-the-temp-password',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect($this->flagged->refresh()->must_change_password)->toBeTrue();
});

test('the temporary password cannot be reused as the new password', function () {
    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'temp-password-1',
            'password_confirmation' => 'temp-password-1',
        ])
        ->assertSessionHasErrors(['password' => 'Choose a new password that is different from your temporary one.']);

    expect($this->flagged->refresh()->must_change_password)->toBeTrue();
});

test('the new password must follow the existing password rules', function () {
    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'my-new-password',
            'password_confirmation' => 'does-not-match',
        ])
        ->assertSessionHasErrors('password');
});

test('a successful change ends the other sessions of the user but keeps the current one', function () {
    config(['session.driver' => 'database']);

    $other = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    foreach ([['other-browser-1', $this->flagged->id], ['other-browser-2', $this->flagged->id], ['someone-else', $other->id]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['other-browser-1', 'other-browser-2'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'someone-else')->count())->toBe(1);

    $this->assertAuthenticatedAs($this->flagged);
});

test('a successful change revokes the mobile tokens of the user', function () {
    $this->flagged->createToken('phone');

    $this->actingAs($this->flagged)
        ->put(route('password.change.update'), [
            'current_password' => 'temp-password-1',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
        ]);

    expect($this->flagged->tokens()->count())->toBe(0);
});

test('a password reset link also clears the flag', function () {
    $token = Password::createToken($this->flagged);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->flagged->email,
        'password' => 'reset-new-password',
        'password_confirmation' => 'reset-new-password',
    ])->assertSessionHasNoErrors();

    expect($this->flagged->refresh()->must_change_password)->toBeFalse();
});

test('endOtherSessions with no session id ends every session for the user', function () {
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert(['id' => 'a', 'user_id' => $this->flagged->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    DB::table('sessions')->insert(['id' => 'b', 'user_id' => $this->flagged->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->flagged->endOtherSessions();

    expect(DB::table('sessions')->where('user_id', $this->flagged->id)->count())->toBe(0);
});
