<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the pre-migration shape (no first_name/last_name columns, only
 * `name`) by rolling the migration back and forward again.
 *
 * @param  list<string>  $names
 * @return array<string, int>
 */
function reapplyFirstAndLastNameMigration(array $names): array
{
    $migration = require database_path('migrations/2026_10_06_120000_add_first_and_last_name_to_users_table.php');
    $migration->down();

    $ids = [];

    foreach ($names as $i => $name) {
        $ids[$name] = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => "legacy{$i}@nu-lipa.edu.ph",
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $migration->up();

    return $ids;
}

test('existing users are split into first and last name, leaving name untouched', function () {
    $ids = reapplyFirstAndLastNameMigration([
        'Carl Justin Magpantay',
        'Pia Jasmin I. Quizon',
        'Juan Dela Cruz',
        'Ana De Los Reyes',
        'Adviser',
    ]);

    $expected = [
        'Carl Justin Magpantay' => ['Carl Justin', 'Magpantay'],
        'Pia Jasmin I. Quizon' => ['Pia Jasmin I.', 'Quizon'],
        'Juan Dela Cruz' => ['Juan', 'Dela Cruz'],
        'Ana De Los Reyes' => ['Ana', 'De Los Reyes'],
        'Adviser' => ['Adviser', null],
    ];

    foreach ($expected as $name => [$first, $last]) {
        $row = DB::table('users')->find($ids[$name]);

        expect($row->name)->toBe($name)
            ->and($row->first_name)->toBe($first)
            ->and($row->last_name)->toBe($last);
    }
});

test('running the migration again does not overwrite a hand-corrected split', function () {
    $ids = reapplyFirstAndLastNameMigration(['Juan Dela Cruz']);

    DB::table('users')->where('id', $ids['Juan Dela Cruz'])->update(['first_name' => 'Juan Dela', 'last_name' => 'Cruz']);

    $migration = require database_path('migrations/2026_10_06_120000_add_first_and_last_name_to_users_table.php');
    $migration->up();

    $row = DB::table('users')->find($ids['Juan Dela Cruz']);
    expect($row->first_name)->toBe('Juan Dela')->and($row->last_name)->toBe('Cruz');
});

test('the columns are nullable so the migration cannot fail on existing rows', function () {
    expect(Schema::hasColumns('users', ['first_name', 'last_name', 'name']))->toBeTrue();

    User::factory()->create(['name' => 'Someone']);

    expect(DB::table('users')->where('name', 'Someone')->value('last_name'))->toBeNull();
});

test('a parenthesised note is removed from name, first_name and last_name together, and logged', function () {
    Log::spy();

    $ids = reapplyFirstAndLastNameMigration(['Maria Dolores C. Evangelista (Associate Dean, Medical Technology)']);

    $row = DB::table('users')->find($ids['Maria Dolores C. Evangelista (Associate Dean, Medical Technology)']);

    expect($row->name)->toBe('Maria Dolores C. Evangelista')
        ->and($row->first_name)->toBe('Maria Dolores C.')
        ->and($row->last_name)->toBe('Evangelista');

    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => str_contains($message, 'removed parenthesised notes')
        && str_contains($context['users'][0], 'Associate Dean, Medical Technology'))->once();
});

test('a title is kept out of first_name but stays in name so display does not change', function () {
    $ids = reapplyFirstAndLastNameMigration(['Dr. Alice Lacorte', 'Engr. Emmanuel P. Maala', 'Ms. Diane Novicio']);

    $dr = DB::table('users')->find($ids['Dr. Alice Lacorte']);
    $engr = DB::table('users')->find($ids['Engr. Emmanuel P. Maala']);
    $ms = DB::table('users')->find($ids['Ms. Diane Novicio']);

    expect($dr)->name->toBe('Dr. Alice Lacorte')->first_name->toBe('Alice')->last_name->toBe('Lacorte')
        ->and($engr)->name->toBe('Engr. Emmanuel P. Maala')->first_name->toBe('Emmanuel P.')->last_name->toBe('Maala')
        ->and($ms)->name->toBe('Ms. Diane Novicio')->first_name->toBe('Diane');
});

test('a row whose first and last name are already set is never touched, even if its name has a note', function () {
    $ids = reapplyFirstAndLastNameMigration(['Someone Else']);

    DB::table('users')->where('id', $ids['Someone Else'])->update([
        'name' => 'Hand Fixed (keep this)',
        'first_name' => 'Hand',
        'last_name' => 'Fixed',
    ]);

    $migration = require database_path('migrations/2026_10_06_120000_add_first_and_last_name_to_users_table.php');
    $migration->up();

    expect(DB::table('users')->find($ids['Someone Else']))
        ->name->toBe('Hand Fixed (keep this)')->first_name->toBe('Hand')->last_name->toBe('Fixed');
});
