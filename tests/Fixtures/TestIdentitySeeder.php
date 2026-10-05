<?php

namespace Tests\Fixtures;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The test suite's base fixture: IdentitySeeder (the SDAO accounts and the
 * adviser pool) plus everything else most tests need, so that none of it can
 * ever be seeded into a dev or demo database:
 *
 *  - the four real schools, with the same names and ranks as RealRosterSeeder;
 *  - placeholder directors and a Senior High principal;
 *  - a dean, two program chairs and two organizations at the first school,
 *    each with an adviser (taken from IdentitySeeder's pool) and a student;
 *  - a Senior High School organization and an Extra-Curricular organization.
 *
 * Seats are keyed on role + scope with firstOrCreate, so combining this with
 * the real roster never creates a second holder.
 */
class TestIdentitySeeder extends Seeder
{
    use WithoutModelEvents;

    private const string PASSWORD = 'ict@1234';

    public function run(): void
    {
        $this->call(IdentitySeeder::class);

        $sace = School::firstOrCreate(['name' => 'School of Architecture, Computing, and Engineering'], ['type' => 'regular', 'academic_rank' => 1]);
        School::firstOrCreate(['name' => 'School of Accountancy, Business, and Management'], ['type' => 'regular', 'academic_rank' => 2]);
        School::firstOrCreate(['name' => 'School of Allied Health and Sciences'], ['type' => 'regular', 'academic_rank' => 3]);
        $shs = School::firstOrCreate(['name' => 'Senior High School'], ['type' => 'senior_high', 'academic_rank' => 4]);

        $this->globalSeat(Role::AssistantDirectorAcademicServices, 'Asst. Director of Academic Services', 'asst-director@nu-lipa.edu.ph');
        $this->globalSeat(Role::AcademicDirector, 'Academic Director', 'academic-director@nu-lipa.edu.ph');
        $this->globalSeat(Role::ExecutiveDirector, 'Executive Director', 'executive-director@nu-lipa.edu.ph');

        $dean = $this->user('Dean CCIT', 'dean-ccit@nu-lipa.edu.ph');
        RoleAssignment::firstOrCreate(
            ['role' => Role::Dean, 'school_id' => $sace->id, 'program_id' => null, 'organization_id' => null],
            ['user_id' => $dean->id],
        );

        $this->organization($sace, 'BS Computer Science', 'Computing Society', 'chair-cs@nu-lipa.edu.ph', 'Chair CS', 'adviser-one@nu-lipa.edu.ph', 'student-alpha@students.nu-lipa.edu.ph', 'Student Alpha');
        $this->organization($sace, 'BS Information Technology', 'IT Guild', 'chair-it@nu-lipa.edu.ph', 'Chair IT', 'adviser-two@nu-lipa.edu.ph', 'student-beta@students.nu-lipa.edu.ph', 'Student Beta');

        $principal = $this->user('Principal SHS', 'principal-shs@nu-lipa.edu.ph');
        RoleAssignment::firstOrCreate(
            ['role' => Role::Principal, 'school_id' => $shs->id, 'program_id' => null, 'organization_id' => null],
            ['user_id' => $principal->id],
        );

        $shsCouncil = Organization::create(['name' => 'SHS Student Council', 'school_id' => $shs->id, 'program_id' => null]);
        $this->bindAdviser('adviser-shs@nu-lipa.edu.ph', $shsCouncil);
        $this->student('Student Gamma', 'student-gamma@students.nu-lipa.edu.ph', $shsCouncil);

        // Extra-Curricular: no college, so school_id and program_id are both null.
        $chessClub = Organization::create(['name' => 'University Chess Club', 'school_id' => null, 'program_id' => null]);
        $adviserExtraCurricular = $this->user('Adviser Extra-Curricular', 'adviser-extracurricular@nu-lipa.edu.ph');
        RoleAssignment::create(['user_id' => $adviserExtraCurricular->id, 'role' => Role::Adviser, 'organization_id' => $chessClub->id]);
        $this->student('Student Epsilon', 'student-epsilon@students.nu-lipa.edu.ph', $chessClub);
    }

    private function globalSeat(Role $role, string $name, string $email): void
    {
        RoleAssignment::firstOrCreate(
            ['role' => $role, 'school_id' => null, 'program_id' => null, 'organization_id' => null],
            ['user_id' => $this->user($name, $email)->id],
        );
    }

    private function organization(School $school, string $programName, string $orgName, string $chairEmail, string $chairName, string $adviserEmail, string $studentEmail, string $studentName): void
    {
        $program = Program::firstOrCreate(['school_id' => $school->id, 'name' => $programName]);
        $chair = $this->user($chairName, $chairEmail);
        RoleAssignment::firstOrCreate(
            ['role' => Role::ProgramChair, 'program_id' => $program->id, 'school_id' => null, 'organization_id' => null],
            ['user_id' => $chair->id],
        );

        $organization = Organization::create(['name' => $orgName, 'school_id' => $school->id, 'program_id' => $program->id]);
        $this->bindAdviser($adviserEmail, $organization);
        $this->student($studentName, $studentEmail, $organization);
    }

    /** Binds an adviser from IdentitySeeder's unassigned pool to an organization. */
    private function bindAdviser(string $email, Organization $organization): void
    {
        RoleAssignment::where('role', Role::Adviser->value)
            ->whereNull('organization_id')
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->update(['organization_id' => $organization->id]);
    }

    private function student(string $name, string $email, Organization $organization): void
    {
        RoleAssignment::create(['user_id' => $this->user($name, $email)->id, 'role' => Role::Student, 'organization_id' => $organization->id]);
    }

    private function user(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
        ]);
    }
}
