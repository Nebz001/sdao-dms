<?php

use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed, with no two-factor props or controls', function () {
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('canManagePasskeys', true)
            ->where('passkeys', [])
            ->missing('canManageTwoFactor')
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation')
            ->missing('twoFactorPendingConfirmation'),
        );
});

test('security page requires password confirmation', function () {
    // Unconditional — RequirePassword::class sits on this route directly
    // (routes/settings.php), independent of any Fortify feature flag.
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page loads for a freshly-provisioned approver, and their password can be changed', function () {
    $sdao = User::factory()->create();
    $sdao->roleAssignments()->create(['role' => Role::SdaoMember]);

    $approver = app(ProvisionApprover::class)->execute(
        actor: $sdao,
        name: 'New Adviser',
        email: 'new-adviser@nu-lipa.edu.ph',
        role: Role::Adviser,
        scope: [],
    );

    // A provisioned approver is forced to change the one time password first
    // (see ChangeTemporaryPasswordTest); this covers the later Settings path.
    $approver->forceFill(['password' => 'old-password', 'must_change_password' => false])->save();

    $this->actingAs($approver)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->missing('canManageTwoFactor'),
        );

    $response = $this->actingAs($approver)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('security.edit'));
    expect(Hash::check('new-password', $approver->refresh()->password))->toBeTrue();
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('flash', ['message' => 'Password changed.'])
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('changing the password ends the other web sessions of the user but keeps the current one', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    foreach ([['second-browser', $user->id], ['third-browser', $user->id], ['someone-elses-browser', $someoneElse->id]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->whereIn('id', ['second-browser', 'third-browser'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'someone-elses-browser')->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', app('session.store')->getId())->where('user_id', $user->id)->count())->toBe(1);

    $this->assertAuthenticatedAs($user);
});

test('a failed password change leaves the other web sessions alone', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'second-browser',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(DB::table('sessions')->where('id', 'second-browser')->count())->toBe(1);
});

test('changing the password still revokes the mobile tokens of the user', function () {
    $user = User::factory()->create();
    $user->createToken('phone');

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->tokens()->count())->toBe(0);
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
