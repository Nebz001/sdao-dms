<?php

use App\Enums\AccountStatus;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

test('an SDAO member can verify a pending account', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(VerifyAccount::class)->execute($this->sdaoA, $account);

    expect($account->fresh()->account_status)->toBe(AccountStatus::Verified);
});

test('an SDAO member can reject a pending account — the row is preserved, never deleted', function () {
    $account = User::factory()->unverifiedAccount()->create();

    app(RejectAccount::class)->execute($this->sdaoA, $account);

    $account->refresh();
    expect($account->account_status)->toBe(AccountStatus::Rejected);
    expect(User::find($account->id))->not->toBeNull();
});

test('a non-SDAO actor cannot verify or reject accounts', function () {
    $account = User::factory()->unverifiedAccount()->create();

    expect(fn () => app(VerifyAccount::class)->execute($this->adviser, $account))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(RejectAccount::class)->execute($this->adviser, $account))
        ->toThrow(AuthorizationException::class);
});

test('an already-verified account cannot be re-verified or rejected', function () {
    $account = User::factory()->create(); // factory default: Verified

    expect(fn () => app(VerifyAccount::class)->execute($this->sdaoA, $account))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RejectAccount::class)->execute($this->sdaoA, $account))
        ->toThrow(ValidationException::class);
});

test('the Pending Accounts queue lists only Unverified accounts', function () {
    $pending = User::factory()->unverifiedAccount()->create(['name' => 'Pending Pat']);
    User::factory()->rejectedAccount()->create(['name' => 'Rejected Ray']);
    User::factory()->create(['name' => 'Verified Val']);

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('admin.pending-accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pending-accounts/index')
            ->has('accounts', 1)
            ->where('accounts.0.name', $pending->name)
        );
});

test('an SDAO member can verify an account end-to-end via HTTP', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $response = $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.verify', $account));

    $response
        ->assertRedirect(route('admin.pending-accounts.index'))
        ->assertSessionHas('flash.title', 'Account verified')
        ->assertSessionHas('flash.actions.0.label', 'Undo');
    expect($account->fresh()->account_status)->toBe(AccountStatus::Verified);
});

test('an SDAO member can reject an account end-to-end via HTTP', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $response = $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.reject', $account));

    $response
        ->assertRedirect(route('admin.pending-accounts.index'))
        ->assertSessionHas('flash.title', 'Account rejected')
        ->assertSessionHas('flash.actions.0.label', 'Undo');
    expect($account->fresh()->account_status)->toBe(AccountStatus::Rejected);
});

// ── Rejecting an already-acted-on account (two admins racing the same target)
// must round-trip through the standard validation-error flash, not a redirect
// success or a raw 500 — this is the exact path the pending-accounts Reject
// dialog's onError now depends on to stay open and show why (see
// resources/js/pages/admin/pending-accounts/index.tsx and PLAN.md "modal
// auto-dismiss" / Bug 5). The unit-level equivalent already exists above
// ("an already-verified account cannot be re-verified or rejected"); this
// pins the same failure at the HTTP layer the frontend actually observes.
test('HTTP: rejecting an already-acted-on account returns a validation error, not a redirect success', function () {
    $account = User::factory()->create(); // factory default: Verified — already acted on

    $this->actingAs($this->sdaoA)
        ->post(route('admin.pending-accounts.reject', $account))
        ->assertSessionHasErrors('account');

    expect($account->fresh()->account_status)->toBe(AccountStatus::Verified);
});

test('a non-SDAO authenticated user gets 403 on every pending-accounts route', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $this->actingAs($this->adviser)->get(route('admin.pending-accounts.index'))->assertForbidden();
    $this->actingAs($this->adviser)->post(route('admin.pending-accounts.verify', $account))->assertForbidden();
    $this->actingAs($this->adviser)->post(route('admin.pending-accounts.reject', $account))->assertForbidden();
});

// ── Undo link on the Verify / Reject toast ────────────────────────────────

test('the undo link on a verify toast puts the account back in the pending queue', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.verify', $account))
        ->assertSessionHas('flash.actions.0', [
            'label' => 'Undo',
            'href' => route('admin.pending-accounts.revert', $account),
            'method' => 'post',
        ]);

    $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.revert', $account))
        ->assertRedirect(route('admin.pending-accounts.index'))
        ->assertSessionHas('flash.title', 'Review undone');

    expect($account->fresh()->account_status)->toBe(AccountStatus::Unverified);
});

test('the undo link on a reject toast puts the account back in the pending queue', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.reject', $account));
    $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.revert', $account));

    expect($account->fresh()->account_status)->toBe(AccountStatus::Unverified);
});

test('a review cannot be undone once the account is bound to an organization', function () {
    $account = User::factory()->unverifiedAccount()->create();
    app(VerifyAccount::class)->execute($this->sdaoA, $account);
    OrganizationMembership::factory()->create(['user_id' => $account->id]);

    $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.revert', $account))
        ->assertSessionHas('flash.type', 'error');

    expect($account->fresh()->account_status)->toBe(AccountStatus::Verified);
});

test('only an SDAO member can undo an account review', function () {
    $account = User::factory()->unverifiedAccount()->create();
    app(RejectAccount::class)->execute($this->sdaoA, $account);

    $this->actingAs($this->adviser)->post(route('admin.pending-accounts.revert', $account))
        ->assertForbidden();

    expect($account->fresh()->account_status)->toBe(AccountStatus::Rejected);
});
