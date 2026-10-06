<?php

use App\Support\PersonName;

dataset('name splits', [
    'plain two words' => ['Juan Cruz', 'Juan', 'Cruz', true],
    'two given names' => ['Carl Justin Magpantay', 'Carl Justin', 'Magpantay', true],
    'middle initial stays with first name' => ['Pia Jasmin I. Quizon', 'Pia Jasmin I.', 'Quizon', true],
    'dela particle' => ['Juan Dela Cruz', 'Juan', 'Dela Cruz', false],
    'de particle' => ['Maria De Leon', 'Maria', 'De Leon', false],
    'del particle' => ['Jose Del Rosario', 'Jose', 'Del Rosario', false],
    'delos particle' => ['Ana Delos Santos', 'Ana', 'Delos Santos', false],
    'de los' => ['Ana De Los Reyes', 'Ana', 'De Los Reyes', false],
    'de la' => ['Ana De La Rosa', 'Ana', 'De La Rosa', false],
    'san particle' => ['Rey San Jose', 'Rey', 'San Jose', false],
    'sta particle with period' => ['Liza Sta. Maria', 'Liza', 'Sta. Maria', false],
    'santa particle' => ['Liza Santa Ana', 'Liza', 'Santa Ana', false],
    'two given names and a particle' => ['Ramon Luis Dela Cruz', 'Ramon Luis', 'Dela Cruz', false],
    'middle initial and a particle' => ['Juan P. Dela Cruz', 'Juan P.', 'Dela Cruz', false],
    'lowercase particle' => ['juan dela cruz', 'juan', 'dela cruz', false],
    'title is removed from the first name' => ['Engr. Michael Roxas', 'Michael', 'Roxas', true],
    'suffix stays with surname' => ['Juan Cruz Jr.', 'Juan', 'Cruz Jr.', false],
    'enye' => ['Íñigo Muñoz', 'Íñigo', 'Muñoz', true],
    'comma form' => ['Dela Cruz, Juan', 'Juan', 'Dela Cruz', false],
    'extra whitespace' => ["  Juan   \u{00A0}Cruz ", 'Juan', 'Cruz', true],
    'trailing note with a comma is ignored' => ['Maria Dolores C. Evangelista (Associate Dean, Medical Technology)', 'Maria Dolores C.', 'Evangelista', false],
    'titles do not count towards the length check' => ['Sir Joseph Michael E. Aramil', 'Joseph Michael E.', 'Aramil', true],
    'Mr.' => ['Mr. Juan Cruz', 'Juan', 'Cruz', true],
    'Ms.' => ['Ms. Maria Santos', 'Maria', 'Santos', true],
    'Mrs.' => ['Mrs. Maria Santos', 'Maria', 'Santos', true],
    'Miss' => ['Miss Maria Santos', 'Maria', 'Santos', true],
    'Dr.' => ['Dr. Alice Lacorte', 'Alice', 'Lacorte', true],
    'Atty.' => ['Atty. Jose Rizal', 'Jose', 'Rizal', true],
    'Prof.' => ['Prof. Jose Rizal', 'Jose', 'Rizal', true],
    'Fr.' => ['Fr. Jose Rizal', 'Jose', 'Rizal', true],
    'leading Sr.' => ['Sr. Maria Santos', 'Maria', 'Santos', true],
    'no period, any case' => ['dr alice lacorte', 'alice', 'lacorte', true],
    'two titles' => ['Dr. Engr. Alice Lacorte', 'Alice', 'Lacorte', true],
    'title with a particle surname' => ['Dr. Juan Dela Cruz', 'Juan', 'Dela Cruz', false],
    'title and a note' => ['Dr. Maria C. Evangelista (Associate Dean, Medical Technology)', 'Maria C.', 'Evangelista', false],
    'trailing Sr. is a suffix, not a title' => ['Juan Cruz Sr.', 'Juan', 'Cruz Sr.', false],
    'a title in the comma form' => ['Cruz, Dr. Juan', 'Juan', 'Cruz', false],
    'one word' => ['Adviser', 'Adviser', '', false],
    'a title and one name' => ['Dr. Alice', 'Alice', '', false],
    'only a title' => ['Dr.', 'Dr.', '', false],
    'five words' => ['Maria Luisa Anna Marie Cruz', 'Maria Luisa Anna Marie', 'Cruz', false],
]);

it('splits a full name into first and last', function (string $full, string $first, string $last, bool $confident) {
    expect(PersonName::split($full))->toMatchArray(['first' => $first, 'last' => $last, 'confident' => $confident]);
})->with('name splits');

it('never leaves a particle as the whole last name', function () {
    expect(PersonName::split('Dela Cruz'))->toMatchArray(['first' => 'Dela', 'last' => 'Cruz']);
});

it('joins first and last, skipping a missing part', function () {
    expect(PersonName::join('Juan', 'Dela Cruz'))->toBe('Juan Dela Cruz')
        ->and(PersonName::join('Adviser', null))->toBe('Adviser')
        ->and(PersonName::join(' Juan ', ' Cruz '))->toBe('Juan Cruz');
});

it('builds users-table attributes with a null last name for a one-word name', function () {
    expect(PersonName::attributes('Pia Jasmin I. Quizon'))->toBe(['name' => 'Pia Jasmin I. Quizon', 'first_name' => 'Pia Jasmin I.', 'last_name' => 'Quizon'])
        ->and(PersonName::attributes('Adviser'))->toBe(['name' => 'Adviser', 'first_name' => 'Adviser', 'last_name' => null]);
});

it('reports the removed title', function () {
    expect(PersonName::split('Dr. Engr. Alice Lacorte')['title'])->toBe('Dr. Engr.')
        ->and(PersonName::split('Alice Lacorte')['title'])->toBe('')
        ->and(PersonName::leadingTitle('Dr. Alice Lacorte (Dean)'))->toBe('Dr.')
        ->and(PersonName::leadingTitle('Alice Lacorte'))->toBe('');
});

it('strips a title typed into a first-name field, but keeps a first name that is only a title', function () {
    expect(PersonName::stripTitle('Dr. Alice'))->toBe('Alice')
        ->and(PersonName::stripTitle('  Mr.   Juan Carlo '))->toBe('Juan Carlo')
        ->and(PersonName::stripTitle('Alice'))->toBe('Alice')
        ->and(PersonName::stripTitle('Dr.'))->toBe('Dr.');
});

it('removes parenthesised notes', function () {
    expect(PersonName::stripNote('Maria C. Evangelista (Associate Dean, Medical Technology)'))->toBe('Maria C. Evangelista')
        ->and(PersonName::stripNote('Juan (a) Cruz (b)'))->toBe('Juan Cruz')
        ->and(PersonName::stripNote('Juan Cruz'))->toBe('Juan Cruz');
});

it('keeps the full name, title included, in the name attribute but not in first_name', function () {
    expect(PersonName::attributes('Dr. Pia Jasmin I. Quizon'))->toBe(['name' => 'Dr. Pia Jasmin I. Quizon', 'first_name' => 'Pia Jasmin I.', 'last_name' => 'Quizon']);
});
