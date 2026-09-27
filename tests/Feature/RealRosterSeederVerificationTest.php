<?php

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RealRosterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('every account RealRosterSeeder creates is email-verified and account-verified', function () {
    $this->seed(RealRosterSeeder::class);

    $users = User::all();

    expect($users)->not->toBeEmpty();

    foreach ($users as $user) {
        expect($user->email_verified_at)->not->toBeNull("{$user->email} has no email_verified_at");
        expect($user->account_status)->toBe(AccountStatus::Verified, "{$user->email} is not Verified");
    }
});

test('rerunning RealRosterSeeder keeps already-verified accounts verified', function () {
    $this->seed(RealRosterSeeder::class);
    $this->seed(RealRosterSeeder::class);

    $users = User::all();

    foreach ($users as $user) {
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->account_status)->toBe(AccountStatus::Verified);
    }
});

test('reseeding fills a null email_verified_at on an existing unverified row', function () {
    $existing = User::factory()->unverified()->create([
        'email' => 'magpantayc@nu-lipa.edu.ph',
        'account_status' => AccountStatus::Unverified,
    ]);

    expect($existing->email_verified_at)->toBeNull();

    $this->seed(RealRosterSeeder::class);

    $existing->refresh();

    expect($existing->email_verified_at)->not->toBeNull();
    expect($existing->account_status)->toBe(AccountStatus::Verified);
});

test('reseeding never un-rejects an account SDAO rejected on purpose', function () {
    $rejected = User::factory()->create([
        'email' => 'magpantayc@nu-lipa.edu.ph',
        'account_status' => AccountStatus::Rejected,
        'email_verified_at' => null,
    ]);

    $this->seed(RealRosterSeeder::class);

    $rejected->refresh();

    // Only the guarded field (account_status) is protected — a still-null
    // email_verified_at is unrelated to the rejection and is filled in like
    // any other gap.
    expect($rejected->account_status)->toBe(AccountStatus::Rejected);
    expect($rejected->email_verified_at)->not->toBeNull();
});

test('reseeding leaves an already-verified timestamp and name/password refresh untouched by status', function () {
    $verifiedAt = now()->subYear();

    $existing = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'magpantayc@nu-lipa.edu.ph',
        'account_status' => AccountStatus::Verified,
        'email_verified_at' => $verifiedAt,
    ]);

    $this->seed(RealRosterSeeder::class);

    $existing->refresh();

    expect($existing->email_verified_at->timestamp)->toBe($verifiedAt->timestamp);
    expect($existing->account_status)->toBe(AccountStatus::Verified);
    // Name (and password) still refresh on every reseed — only
    // email_verified_at/account_status are gap-filled, not frozen entirely.
    expect($existing->name)->toBe('Carl Justin Magpantay');
});
