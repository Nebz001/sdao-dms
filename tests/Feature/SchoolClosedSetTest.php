<?php

use App\Enums\SchoolType;
use App\Models\School;

/**
 * Structural fix (2026-09-09 plan): schools are hard-closed to exactly 4 real
 * rows (SACE, SAHS, SABM, SHS) via a model-level guard scoped to the real
 * Postgres connection only — never sqlite, since OrganizationFactory/
 * ProgramFactory/IdentitySeeder all freely mint fresh throwaway schools for
 * test isolation under the Pest suite's own in-memory DB.
 *
 * That scoping means the guard's actual throw path cannot be exercised here
 * (this suite always runs on sqlite) — School::enforcesClosedSet() is
 * false in this environment by design, which these tests pin directly. The
 * pure counting rule (wouldExceedClosedSet) is fully driver-agnostic and
 * IS tested end to end. The pgsql-gated throw itself was verified manually
 * against the real dev database.
 */
test('enforcesClosedSet is false under the test suite\'s sqlite connection', function () {
    $school = School::factory()->make();

    expect($school->enforcesClosedSet())->toBeFalse();
});

test('wouldExceedClosedSet is false below 4 schools and true at 4 or more', function () {
    expect(School::wouldExceedClosedSet())->toBeFalse();

    School::factory()->count(4)->create();

    expect(School::count())->toBe(4);
    expect(School::wouldExceedClosedSet())->toBeTrue();
});

test('creating a 5th school is NOT blocked under sqlite — test fixtures are unaffected', function () {
    School::factory()->count(5)->create();

    expect(School::count())->toBe(5);
});

test('deleting a school is NOT blocked under sqlite — test fixtures are unaffected', function () {
    $school = School::factory()->create();

    $school->delete();

    expect(School::find($school->id))->toBeNull();
});

test('type casts to and from SchoolType correctly', function () {
    $regular = School::factory()->create(['type' => SchoolType::Regular]);
    $seniorHigh = School::factory()->seniorHigh()->create();

    expect($regular->fresh()->type)->toBe(SchoolType::Regular);
    expect($seniorHigh->fresh()->type)->toBe(SchoolType::SeniorHigh);
    expect($regular->fresh()->isRegular())->toBeTrue();
    expect($seniorHigh->fresh()->isSeniorHigh())->toBeTrue();
});
