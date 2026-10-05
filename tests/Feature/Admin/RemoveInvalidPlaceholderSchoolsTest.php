<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\RealRosterSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Hash;

const FAKE_SCHOOL_NAMES = ['School of Computing and IT', 'School of Business and Accountancy', 'School of Health Sciences'];

const LEGACY_PLACEHOLDER_EMAILS = [
    'asst-director@nu-lipa.edu.ph',
    'academic-director@nu-lipa.edu.ph',
    'executive-director@nu-lipa.edu.ph',
    'principal-shs@nu-lipa.edu.ph',
    'adviser-extracurricular@nu-lipa.edu.ph',
    'student-alpha@students.nu-lipa.edu.ph',
    'student-beta@students.nu-lipa.edu.ph',
    'student-gamma@students.nu-lipa.edu.ph',
    'student-epsilon@students.nu-lipa.edu.ph',
];

function removeInvalidSchoolsMigration(): object
{
    return require database_path('migrations/2026_10_05_100000_remove_invalid_placeholder_schools.php');
}

/**
 * A dev database as it was: the real roster and IdentitySeeder, plus the
 * placeholder data the old IdentitySeeder left behind.
 */
function oldPlaceholderData(): array
{
    $school = School::create(['name' => 'School of Computing and IT', 'type' => 'regular', 'academic_rank' => null]);
    School::create(['name' => 'School of Business and Accountancy', 'type' => 'regular', 'academic_rank' => null]);
    School::create(['name' => 'School of Health Sciences', 'type' => 'regular', 'academic_rank' => null]);
    $program = Program::create(['school_id' => $school->id, 'name' => 'BS Placeholder']);

    $dean = User::factory()->create(['email' => 'dean-ccit@nu-lipa.edu.ph']);
    $chair = User::factory()->create(['email' => 'chair-cs@nu-lipa.edu.ph']);
    RoleAssignment::create(['user_id' => $dean->id, 'role' => Role::Dean, 'school_id' => $school->id]);
    RoleAssignment::create(['user_id' => $chair->id, 'role' => Role::ProgramChair, 'program_id' => $program->id]);

    $organization = Organization::create(['name' => 'Placeholder Club', 'school_id' => $school->id, 'program_id' => $program->id]);
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser, 'organization_id' => $organization->id]);
    $student = User::factory()->create();
    RoleAssignment::create(['user_id' => $student->id, 'role' => Role::Student, 'organization_id' => $organization->id]);

    // The invented accounts the old seeder also created. The directors and the
    // principal held no seat (the real roster's seat won), the rest are pool or orgless.
    foreach (LEGACY_PLACEHOLDER_EMAILS as $email) {
        $user = User::factory()->create(['email' => $email, 'password' => Hash::make('ict@1234')]);

        if (str_starts_with($email, 'student-')) {
            RoleAssignment::create(['user_id' => $user->id, 'role' => Role::Student]);
        } elseif ($email === 'adviser-extracurricular@nu-lipa.edu.ph') {
            RoleAssignment::create(['user_id' => $user->id, 'role' => Role::Adviser]);
        }
    }

    return compact('school', 'program', 'dean', 'chair', 'organization', 'adviser', 'student');
}

beforeEach(function () {
    $this->seed([SettingsSeeder::class, RealRosterSeeder::class, WorkflowTemplateSeeder::class, IdentitySeeder::class]);
});

test('the migration leaves nothing of the placeholder data behind and keeps every real record', function () {
    $old = oldPlaceholderData();
    $removedUserIds = User::whereIn('email', [...LEGACY_PLACEHOLDER_EMAILS, 'dean-ccit@nu-lipa.edu.ph', 'chair-cs@nu-lipa.edu.ph'])
        ->pluck('id')->push($old['student']->id)->all();
    $kept = User::whereNotIn('id', $removedUserIds)->count();
    $realSchools = School::whereNotIn('name', FAKE_SCHOOL_NAMES)->count();
    $realSeats = RoleAssignment::whereNotIn('user_id', [...$removedUserIds, $old['adviser']->id])->count();

    removeInvalidSchoolsMigration()->up();

    expect(School::whereIn('name', FAKE_SCHOOL_NAMES)->exists())->toBeFalse()
        ->and(Program::find($old['program']->id))->toBeNull()
        ->and(Organization::find($old['organization']->id))->toBeNull()
        ->and(User::whereIn('id', $removedUserIds)->exists())->toBeFalse()
        ->and(RoleAssignment::where('school_id', $old['school']->id)->orWhere('program_id', $old['program']->id)->exists())->toBeFalse()
        // Real data untouched.
        ->and(School::whereNotIn('name', FAKE_SCHOOL_NAMES)->count())->toBe($realSchools)
        ->and(User::count())->toBe($kept)
        ->and(RoleAssignment::whereNotIn('user_id', [$old['adviser']->id])->count())->toBe($realSeats)
        ->and(User::whereIn('email', ['sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph', 'adviser-one@nu-lipa.edu.ph', 'adviser-two@nu-lipa.edu.ph', 'adviser-shs@nu-lipa.edu.ph'])->count())->toBe(5);

    // An adviser account is never deleted: it only loses the removed organization.
    expect(User::find($old['adviser']->id))->not->toBeNull()
        ->and(RoleAssignment::where('user_id', $old['adviser']->id)->first()->organization_id)->toBeNull();
});

test('the dry run lists every row by id and name and changes nothing', function () {
    $old = oldPlaceholderData();
    $users = User::count();
    $seats = RoleAssignment::count();
    $schools = School::count();

    $lines = implode("\n", removeInvalidSchoolsMigration()->dryRun());

    expect($lines)->toContain('DRY RUN')
        ->toContain("#{$old['school']->id} School of Computing and IT")
        ->toContain("#{$old['program']->id} BS Placeholder")
        ->toContain("#{$old['organization']->id} Placeholder Club")
        ->toContain('dean-ccit@nu-lipa.edu.ph')
        ->toContain('student-gamma@students.nu-lipa.edu.ph')
        ->toContain('RESULT: all checks pass')
        ->and(User::count())->toBe($users)
        ->and(RoleAssignment::count())->toBe($seats)
        ->and(School::count())->toBe($schools);
});

test('the migration keeps an account that also holds a seat at a real school, minus its fake seat', function () {
    $old = oldPlaceholderData();
    $real = School::where('name', 'School of Allied Health and Sciences')->firstOrFail();
    RoleAssignment::create(['user_id' => $old['dean']->id, 'role' => Role::Dean, 'school_id' => $real->id]);

    removeInvalidSchoolsMigration()->up();

    expect(User::find($old['dean']->id))->not->toBeNull()
        ->and(RoleAssignment::where('user_id', $old['dean']->id)->where('school_id', $real->id)->exists())->toBeTrue();
});

test('the migration refuses, and changes nothing, while a document is attached to a placeholder organization', function () {
    $old = oldPlaceholderData();
    Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $old['organization']->id,
        'status' => DocumentStatus::InReview,
    ]);

    expect(fn () => removeInvalidSchoolsMigration()->up())->toThrow(RuntimeException::class, '1 documents on the removed organizations');
    expect(removeInvalidSchoolsMigration()->dryRun())->toContain('RESULT: BLOCKED. The migration would refuse and change nothing.');

    expect(School::where('name', 'School of Computing and IT')->exists())->toBeTrue()
        ->and(Organization::find($old['organization']->id))->not->toBeNull()
        ->and(User::find($old['dean']->id))->not->toBeNull();
});

test('the migration refuses, and changes nothing, when a removed account has history', function () {
    $old = oldPlaceholderData();
    $document = Document::factory()->create(['form_type' => FormType::OrganizationRegistration, 'status' => DocumentStatus::InReview]);
    DocumentTransition::factory()->create(['document_id' => $document->id, 'actor_id' => $old['dean']->id, 'created_at' => now()]);

    expect(fn () => removeInvalidSchoolsMigration()->up())->toThrow(RuntimeException::class, 'transitions by the removed accounts');

    expect(School::where('name', 'School of Computing and IT')->exists())->toBeTrue()
        ->and(User::find($old['dean']->id))->not->toBeNull();
});

test('the migration refuses when a placeholder student is a member of a real organization', function () {
    oldPlaceholderData();
    $alpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    OrganizationMembership::factory()->create(['user_id' => $alpha->id, 'organization_id' => Organization::factory()->create()->id]);

    expect(fn () => removeInvalidSchoolsMigration()->up())->toThrow(RuntimeException::class, 'memberships of the removed accounts elsewhere');
});

test('a placeholder director that is the only holder of its seat is kept, not deleted', function () {
    oldPlaceholderData();
    $placeholder = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    RoleAssignment::where('role', Role::AssistantDirectorAcademicServices->value)->delete();
    RoleAssignment::create(['user_id' => $placeholder->id, 'role' => Role::AssistantDirectorAcademicServices]);

    $plan = removeInvalidSchoolsMigration()->plan();

    expect(collect($plan['kept'])->pluck('email')->all())->toContain('asst-director@nu-lipa.edu.ph');

    removeInvalidSchoolsMigration()->up();

    expect(User::find($placeholder->id))->not->toBeNull();
});

test('a student account with a placeholder email but a seat at a real organization is kept', function () {
    oldPlaceholderData();
    $alpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    RoleAssignment::where('user_id', $alpha->id)->delete();
    RoleAssignment::create(['user_id' => $alpha->id, 'role' => Role::Student, 'organization_id' => Organization::factory()->create()->id]);

    removeInvalidSchoolsMigration()->up();

    expect(User::find($alpha->id))->not->toBeNull();
});

test('the migration does nothing on a database without the placeholder data', function () {
    $schools = School::count();
    $users = User::count();

    removeInvalidSchoolsMigration()->up();

    expect(School::count())->toBe($schools)->and(User::count())->toBe($users);
});
