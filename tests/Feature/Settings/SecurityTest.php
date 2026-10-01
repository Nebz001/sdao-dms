<?php

use App\Enums\Role;
use App\Identity\Admin\ProvisionApprover;
use App\Models\User;
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
