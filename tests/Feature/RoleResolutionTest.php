<?php

use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeRegularSchool(): School
{
    return School::factory()->create(['type' => 'regular']);
}

function makeSeniorHighSchool(): School
{
    return School::factory()->create(['type' => 'senior_high']);
}

function assignRole(User $user, Role $role, array $scope = []): void
{
    RoleAssignment::create(array_merge(['user_id' => $user->id, 'role' => $role], $scope));
}

// ── Regular school resolution ────────────────────────────────────────────────

test('resolves adviser for a regular-school organization', function () {
    $school = makeRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $adviser = User::factory()->create();
    assignRole($adviser, Role::Adviser, ['organization_id' => $org->id]);

    $directory = app(RoleDirectory::class);

    expect($directory->adviserFor($org)->id)->toBe($adviser->id);
});

test('resolves program chair for a regular-school organization', function () {
    $school = makeRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $chair = User::factory()->create();
    assignRole($chair, Role::ProgramChair, ['program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    expect($directory->programChairFor($org)->id)->toBe($chair->id);
});

test('resolves dean for a regular-school organization', function () {
    $school = makeRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);
    $dean = User::factory()->create();
    assignRole($dean, Role::Dean, ['school_id' => $school->id]);

    $directory = app(RoleDirectory::class);

    expect($directory->deanFor($org)->id)->toBe($dean->id);
});

// ── SHS resolution ───────────────────────────────────────────────────────────

test('resolves principal for an SHS organization', function () {
    $shs = makeSeniorHighSchool();
    $org = Organization::factory()->create(['school_id' => $shs->id, 'program_id' => null]);
    $principal = User::factory()->create();
    assignRole($principal, Role::Principal, ['school_id' => $shs->id]);

    $directory = app(RoleDirectory::class);

    expect($directory->principalFor($org)->id)->toBe($principal->id);
});

test('throws when requesting program chair for an SHS organization', function () {
    $shs = makeSeniorHighSchool();
    $org = Organization::factory()->create(['school_id' => $shs->id, 'program_id' => null]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->programChairFor($org))->toThrow(LogicException::class);
});

test('throws when requesting dean for an SHS organization', function () {
    $shs = makeSeniorHighSchool();
    $org = Organization::factory()->create(['school_id' => $shs->id, 'program_id' => null]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->deanFor($org))->toThrow(LogicException::class);
});

test('throws when requesting principal for a regular-school organization', function () {
    $school = makeRegularSchool();
    $program = Program::factory()->create(['school_id' => $school->id]);
    $org = Organization::factory()->create(['school_id' => $school->id, 'program_id' => $program->id]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->principalFor($org))->toThrow(LogicException::class);
});

// ── College-less (Extra-Curricular) resolution ──────────────────────────────
//
// Before Phase 2 remediation item 3, belongsToSeniorHighSchool() checked
// `program_id === null` — true for a genuine SHS org, but ALSO true for a
// college-less org (which has no program either), so it would have been
// misrouted as SHS. These pin the corrected behavior: a college-less org is
// neither regular nor SHS.

test('a college-less organization does not resolve as Senior High School', function () {
    $org = Organization::factory()->create(['school_id' => null, 'program_id' => null]);

    expect($org->belongsToSeniorHighSchool())->toBeFalse();
    expect($org->hasNoSchool())->toBeTrue();
});

test('throws when requesting program chair for a college-less organization', function () {
    $org = Organization::factory()->create(['school_id' => null, 'program_id' => null]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->programChairFor($org))->toThrow(LogicException::class);
});

test('throws when requesting dean for a college-less organization', function () {
    $org = Organization::factory()->create(['school_id' => null, 'program_id' => null]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->deanFor($org))->toThrow(LogicException::class);
});

test('throws when requesting principal for a college-less organization', function () {
    $org = Organization::factory()->create(['school_id' => null, 'program_id' => null]);

    $directory = app(RoleDirectory::class);

    expect(fn () => $directory->principalFor($org))->toThrow(LogicException::class);
});

test('still resolves adviser for a college-less organization — org-scoped, not school-scoped', function () {
    $org = Organization::factory()->create(['school_id' => null, 'program_id' => null]);
    $adviser = User::factory()->create();
    assignRole($adviser, Role::Adviser, ['organization_id' => $org->id]);

    $directory = app(RoleDirectory::class);

    expect($directory->adviserFor($org)->id)->toBe($adviser->id);
});

// ── Global roles ─────────────────────────────────────────────────────────────

test('resolves both SDAO members and returns exactly two', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    assignRole($a, Role::SdaoMember);
    assignRole($b, Role::SdaoMember);

    $directory = app(RoleDirectory::class);
    $members = $directory->sdaoMembers();

    expect($members)->toHaveCount(2);
    expect($members->pluck('id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('resolves each global director role to exactly one user', function () {
    $asstDir = User::factory()->create();
    $acadDir = User::factory()->create();
    $execDir = User::factory()->create();
    assignRole($asstDir, Role::AssistantDirectorAcademicServices);
    assignRole($acadDir, Role::AcademicDirector);
    assignRole($execDir, Role::ExecutiveDirector);

    $directory = app(RoleDirectory::class);

    expect($directory->assistantDirectorAcademicServices()->id)->toBe($asstDir->id);
    expect($directory->academicDirector()->id)->toBe($acadDir->id);
    expect($directory->executiveDirector()->id)->toBe($execDir->id);
});

// A duplicate global-role assignment is a data-quality bug that has
// genuinely occurred in the real dev database (IdentitySeeder's placeholder
// and RealRosterSeeder's real account both assigned the same single-holder
// global role — see RoleDirectory::resolveGlobal()'s docblock).
// resolveGlobal() cannot repair a duplicate, but must resolve it
// deterministically by lowest id (first-assigned), never highest — this
// pins that direction specifically, since "latest wins" was the original,
// incorrect draft of this fix and would silently replace the real named
// approver with a stale placeholder on this exact data.
test('a duplicate global-role assignment resolves to the FIRST-assigned (lowest id) holder, not the latest', function () {
    $real = User::factory()->create();
    $placeholder = User::factory()->create();

    // Created in this order so $real's row gets the lower id, exactly as it
    // does in production (RealRosterSeeder always seeds before
    // IdentitySeeder's placeholder).
    RoleAssignment::create(['user_id' => $real->id, 'role' => Role::AssistantDirectorAcademicServices]);
    RoleAssignment::create(['user_id' => $placeholder->id, 'role' => Role::AssistantDirectorAcademicServices]);

    $directory = app(RoleDirectory::class);

    expect($directory->assistantDirectorAcademicServices()->id)->toBe($real->id);
});
