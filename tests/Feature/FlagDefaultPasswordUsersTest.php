<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->onDefault = User::factory()->create(['email' => 'on-default@nu-lipa.edu.ph', 'password' => 'ict@1234']);
    $this->onDefaultToo = User::factory()->create(['email' => 'also-default@nu-lipa.edu.ph', 'password' => 'ict@1234']);
    $this->safe = User::factory()->create(['email' => 'safe@nu-lipa.edu.ph', 'password' => 'a-strong-private-one']);
});

test('a dry run reports the count and emails and writes nothing', function () {
    $this->artisan('accounts:flag-default-password-users --dry-run')
        ->expectsOutputToContain('Would flag: 2')
        ->expectsOutputToContain('on-default@nu-lipa.edu.ph')
        ->expectsOutputToContain('also-default@nu-lipa.edu.ph')
        ->doesntExpectOutputToContain('safe@nu-lipa.edu.ph')
        ->doesntExpectOutputToContain('ict@1234')
        ->assertSuccessful();

    expect(User::where('must_change_password', true)->count())->toBe(0);
});

test('a real run flags only the accounts on the retired default', function () {
    $this->artisan('accounts:flag-default-password-users --force')->assertSuccessful();

    expect($this->onDefault->refresh()->must_change_password)->toBeTrue()
        ->and($this->onDefaultToo->refresh()->must_change_password)->toBeTrue()
        ->and($this->safe->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('a-strong-private-one', $this->safe->password))->toBeTrue();
});

test('--except skips the named emails in any letter case', function () {
    $this->artisan('accounts:flag-default-password-users --force --except=ON-DEFAULT@nu-lipa.edu.ph')
        ->expectsOutputToContain('Flagging: 1')
        ->expectsOutputToContain('Skipped with --except: 1')
        ->assertSuccessful();

    expect($this->onDefault->refresh()->must_change_password)->toBeFalse()
        ->and($this->onDefaultToo->refresh()->must_change_password)->toBeTrue();
});

test('the command is idempotent', function () {
    $this->artisan('accounts:flag-default-password-users --force')->assertSuccessful();

    $this->artisan('accounts:flag-default-password-users --dry-run')
        ->expectsOutputToContain('Would flag: 0')
        ->assertSuccessful();
});

test('declining the confirmation changes nothing', function () {
    $this->artisan('accounts:flag-default-password-users')
        ->expectsConfirmation('Flag these accounts so they must change their password at next login?', 'no')
        ->assertSuccessful();

    expect(User::where('must_change_password', true)->count())->toBe(0);
});
