<?php

use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * RoleDirectory::remembering() is opt-in, request-scoped memoization — off
 * by default, so a lookup made outside it always hits the database exactly
 * as before it existed. These tests prove: (1) off by default is really
 * off, (2) it actually saves queries when turned on, (3) different scopes
 * are never confused with each other, (4) a failed lookup is never cached
 * as if it had succeeded, and (5) the cache doesn't leak past its own
 * callback, including when nested.
 */
function makeMemoRegularSchool(): School
{
    return School::factory()->create(['type' => 'regular']);
}

function assignMemoRole(User $user, Role $role, array $scope = []): void
{
    RoleAssignment::create(array_merge(['user_id' => $user->id, 'role' => $role], $scope));
}

function countRoleAssignmentQueries(): int
{
    return count(array_filter(
        DB::getQueryLog(),
        fn ($entry) => str_contains($entry['query'], 'role_assignments'),
    ));
}

test('outside remembering(), repeated lookups are not cached — each call queries again', function () {
    $school = makeMemoRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $chair = User::factory()->create();
    assignMemoRole($chair, Role::ProgramChair, ['program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    DB::enableQueryLog();

    $first = $directory->programChairFor($org);
    $countAfterFirst = countRoleAssignmentQueries();

    $second = $directory->programChairFor($org);
    $countAfterSecond = countRoleAssignmentQueries();

    expect($first->id)->toBe($chair->id);
    expect($second->id)->toBe($chair->id);
    // The second call issued its own fresh query — no caching happened.
    expect($countAfterSecond)->toBeGreaterThan($countAfterFirst);
});

test('inside remembering(), a repeated lookup for the same scope issues only one query', function () {
    $school = makeMemoRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $chair = User::factory()->create();
    assignMemoRole($chair, Role::ProgramChair, ['program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    DB::enableQueryLog();

    $result = $directory->remembering(function () use ($directory, $org, $chair) {
        $first = $directory->programChairFor($org);
        $countAfterFirst = countRoleAssignmentQueries();

        $second = $directory->programChairFor($org);
        $third = $directory->programChairFor($org);
        $countAfterRepeats = countRoleAssignmentQueries();

        expect($first->id)->toBe($chair->id);
        expect($second->id)->toBe($chair->id);
        expect($third->id)->toBe($chair->id);
        // No new role_assignments query for the 2nd/3rd call.
        expect($countAfterRepeats)->toBe($countAfterFirst);

        return true;
    });

    expect($result)->toBeTrue();
});

test('remembering() never confuses two different scopes for the same role', function () {
    $school = makeMemoRegularSchool();
    $programA = Program::factory()->create(['school_id' => $school->id]);
    $programB = Program::factory()->create(['school_id' => $school->id]);
    $orgA = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $programA->id]);
    $orgB = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $programB->id]);
    $chairA = User::factory()->create();
    $chairB = User::factory()->create();
    assignMemoRole($chairA, Role::ProgramChair, ['program_id' => $programA->id]);
    assignMemoRole($chairB, Role::ProgramChair, ['program_id' => $programB->id]);

    $directory = app(RoleDirectory::class);

    $directory->remembering(function () use ($directory, $orgA, $orgB, $chairA, $chairB) {
        expect($directory->programChairFor($orgA)->id)->toBe($chairA->id);
        expect($directory->programChairFor($orgB)->id)->toBe($chairB->id);
        // Re-check both again — confirms the cache didn't overwrite one
        // scope's entry with the other's.
        expect($directory->programChairFor($orgA)->id)->toBe($chairA->id);
        expect($directory->programChairFor($orgB)->id)->toBe($chairB->id);
    });
});

test('remembering() never caches a failed lookup as a success', function () {
    $school = makeMemoRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    // No program chair assigned — every lookup must throw, every time.

    $directory = app(RoleDirectory::class);

    $directory->remembering(function () use ($directory, $org) {
        expect(fn () => $directory->programChairFor($org))->toThrow(ModelNotFoundException::class);
        expect(fn () => $directory->programChairFor($org))->toThrow(ModelNotFoundException::class);
    });
});

test('remembering() caches sdaoMembers() too', function () {
    $sdaoA = User::factory()->create();
    $sdaoB = User::factory()->create();
    assignMemoRole($sdaoA, Role::SdaoMember);
    assignMemoRole($sdaoB, Role::SdaoMember);

    $directory = app(RoleDirectory::class);

    DB::enableQueryLog();

    $directory->remembering(function () use ($directory) {
        $first = $directory->sdaoMembers();
        $countAfterFirst = count(DB::getQueryLog());

        $second = $directory->sdaoMembers();
        $countAfterSecond = count(DB::getQueryLog());

        expect($first->pluck('id')->sort()->values())
            ->toEqual($second->pluck('id')->sort()->values());
        expect($countAfterSecond)->toBe($countAfterFirst);
    });
});

test('the cache does not leak past remembering() — a later outer call queries fresh again', function () {
    $school = makeMemoRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $chairV1 = User::factory()->create();
    assignMemoRole($chairV1, Role::ProgramChair, ['program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    $directory->remembering(function () use ($directory, $org, $chairV1) {
        expect($directory->programChairFor($org)->id)->toBe($chairV1->id);
    });

    // Change who holds the seat between the two remembering() calls.
    RoleAssignment::where('program_id', $program->id)->where('role', Role::ProgramChair)->delete();
    $chairV2 = User::factory()->create();
    assignMemoRole($chairV2, Role::ProgramChair, ['program_id' => $program->id]);

    // A fresh remembering() call must see the NEW holder, not a stale
    // cached value from the first call — proves the cache was cleared.
    $directory->remembering(function () use ($directory, $org, $chairV2) {
        expect($directory->programChairFor($org)->id)->toBe($chairV2->id);
    });
});

test('a nested remembering() call does not clear the outer cache early', function () {
    $school = makeMemoRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $chair = User::factory()->create();
    assignMemoRole($chair, Role::ProgramChair, ['program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    DB::enableQueryLog();

    $directory->remembering(function () use ($directory, $org) {
        $directory->programChairFor($org);
        $countAfterOuterFirst = countRoleAssignmentQueries();

        // A nested remembering() call (e.g. a helper that also opts in)
        // must not wipe the outer scope's cache when IT finishes.
        $directory->remembering(function () use ($directory, $org) {
            $directory->programChairFor($org);
        });

        $directory->programChairFor($org);
        $countAfterOuterSecond = countRoleAssignmentQueries();

        expect($countAfterOuterSecond)->toBe($countAfterOuterFirst);
    });
});
