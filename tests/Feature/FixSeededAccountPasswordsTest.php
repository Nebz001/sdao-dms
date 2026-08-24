<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * See app/Console/Commands/FixSeededAccountPasswords.php — the one-off fix
 * for accounts IdentitySeeder/MembershipSeeder created before they were
 * patched to hash "ict@1234" explicitly. UserFactory's own default password
 * IS "password" (see UserFactory), which is exactly the bug being fixed
 * here, so every user in these tests must set a password explicitly rather
 * than relying on that default — a fixture accidentally left on the
 * default would make "broken" and "already fixed" indistinguishable.
 */
test('fixes the password for a known placeholder account still on the broken default', function () {
    User::factory()->create([
        'name' => 'Adviser One',
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => Hash::make('password'),
    ]);

    $this->artisan('accounts:fix-seeded-passwords', ['--force' => true])
        ->assertSuccessful();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect(Hash::check('ict@1234', $user->password))->toBeTrue();
});

test('is a no-op for an account already on the correct password', function () {
    $originalHash = Hash::make('ict@1234');
    User::factory()->create([
        'name' => 'Adviser One',
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => $originalHash,
    ]);

    $this->artisan('accounts:fix-seeded-passwords', ['--force' => true])
        ->assertSuccessful();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect($user->password)->toBe($originalHash);
});

test('skips an email on the allow-list that does not exist, without error', function () {
    // No users seeded at all — every allow-listed email is "missing".
    $this->artisan('accounts:fix-seeded-passwords', ['--force' => true])
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('refuses to touch an account whose name does not match the expected placeholder name', function () {
    User::factory()->create([
        'name' => 'Someone Else Entirely',
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => Hash::make('password'),
    ]);

    $this->artisan('accounts:fix-seeded-passwords', ['--force' => true])
        ->assertSuccessful();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect(Hash::check('ict@1234', $user->password))->toBeFalse();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('dry run reports what would change without writing anything', function () {
    $brokenHash = Hash::make('password');
    User::factory()->create([
        'name' => 'Adviser One',
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => $brokenHash,
    ]);

    $this->artisan('accounts:fix-seeded-passwords', ['--dry-run' => true])
        ->assertSuccessful();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect($user->password)->toBe($brokenHash);
});

test('prompts for confirmation without --force or --dry-run, and aborts on decline', function () {
    $brokenHash = Hash::make('password');
    User::factory()->create([
        'name' => 'Adviser One',
        'email' => 'adviser-one@nu-lipa.edu.ph',
        'password' => $brokenHash,
    ]);

    $this->artisan('accounts:fix-seeded-passwords')
        ->expectsConfirmation('This re-hashes the password to "ict@1234" for exactly the known IdentitySeeder/MembershipSeeder placeholder accounts (matched by exact email AND name — anything else is skipped, never guessed). No other data is touched. Continue?', 'no')
        ->assertSuccessful();

    $user = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    expect($user->password)->toBe($brokenHash);
});
