<?php

use App\Enums\AccountStatus;
use App\Identity\Admin\RejectAccount;
use App\Identity\Admin\VerifyAccount;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
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

    $response->assertRedirect(route('admin.pending-accounts.index'));
    expect($account->fresh()->account_status)->toBe(AccountStatus::Verified);
});

test('an SDAO member can reject an account end-to-end via HTTP', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $response = $this->actingAs($this->sdaoA)->post(route('admin.pending-accounts.reject', $account));

    $response->assertRedirect(route('admin.pending-accounts.index'));
    expect($account->fresh()->account_status)->toBe(AccountStatus::Rejected);
});

test('a non-SDAO authenticated user gets 403 on every pending-accounts route', function () {
    $account = User::factory()->unverifiedAccount()->create();

    $this->actingAs($this->adviser)->get(route('admin.pending-accounts.index'))->assertForbidden();
    $this->actingAs($this->adviser)->post(route('admin.pending-accounts.verify', $account))->assertForbidden();
    $this->actingAs($this->adviser)->post(route('admin.pending-accounts.reject', $account))->assertForbidden();
});
