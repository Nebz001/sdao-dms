<?php

use App\Models\School;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\RealRosterSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Database\QueryException;

/*
 * "Senior High School" was created with an unconditional School::create(...)
 * in IdentitySeeder, while RealRosterSeeder created the same name with
 * firstOrCreate(...). Running both against one database — the documented
 * "WorkflowTemplateSeeder -> db:seed IdentitySeeder -> demo:reset" restore
 * sequence runs RealRosterSeeder (via the base db:seed) before IdentitySeeder
 * — left a duplicate "Senior High School" row behind, which the registration
 * form's College dropdown then rendered twice. Both halves of the fix get
 * their own coverage here: IdentitySeeder is now idempotent against a school
 * RealRosterSeeder already created, and a unique index makes any other path
 * structurally unable to reintroduce the duplicate.
 */
test('IdentitySeeder reuses the school RealRosterSeeder already created instead of duplicating it', function () {
    $this->seed(WorkflowTemplateSeeder::class);
    $this->seed(RealRosterSeeder::class);

    $this->seed(IdentitySeeder::class);

    expect(School::where('name', 'Senior High School')->count())->toBe(1);
});

test('schools.name has a unique constraint preventing any other duplicate', function () {
    School::create(['name' => 'Senior High School', 'type' => 'senior_high']);

    expect(fn () => School::create(['name' => 'Senior High School', 'type' => 'senior_high']))
        ->toThrow(QueryException::class);
});
