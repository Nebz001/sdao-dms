<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds all users, schools, programs, organizations, and role assignments
 * needed to exercise every approval-chain variant:
 *   - Regular school: on-calendar and off-calendar (adviser → chair → dean → SDAO → 3 directors)
 *   - SHS: on-calendar and off-calendar (adviser → principal → SDAO → 3 directors)
 *   - Short chains (registration, renewal, calendar, after-activity report)
 */
class IdentitySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Global approvers ─────────────────────────────────────────────────

        $sdaoA = $this->user('SDAO Member A', 'sdao-a@sdao.test');
        $sdaoB = $this->user('SDAO Member B', 'sdao-b@sdao.test');
        $asstDirector = $this->user('Asst. Director of Academic Services', 'asst-director@sdao.test');
        $academicDirector = $this->user('Academic Director', 'academic-director@sdao.test');
        $executiveDirector = $this->user('Executive Director', 'executive-director@sdao.test');

        RoleAssignment::create(['user_id' => $sdaoA->id, 'role' => Role::SdaoMember]);
        RoleAssignment::create(['user_id' => $sdaoB->id, 'role' => Role::SdaoMember]);
        RoleAssignment::create(['user_id' => $asstDirector->id, 'role' => Role::AssistantDirectorAcademicServices]);
        RoleAssignment::create(['user_id' => $academicDirector->id, 'role' => Role::AcademicDirector]);
        RoleAssignment::create(['user_id' => $executiveDirector->id, 'role' => Role::ExecutiveDirector]);

        // ── Regular school: School of Computing and IT (CCIT) ────────────────

        $ccit = School::create(['name' => 'School of Computing and IT', 'type' => 'regular']);
        $dean = $this->user('Dean CCIT', 'dean-ccit@sdao.test');
        RoleAssignment::create(['user_id' => $dean->id, 'role' => Role::Dean, 'school_id' => $ccit->id]);

        // Program: BS Computer Science
        $bscs = Program::create(['school_id' => $ccit->id, 'name' => 'BS Computer Science']);
        $chairCs = $this->user('Chair CS', 'chair-cs@sdao.test');
        RoleAssignment::create(['user_id' => $chairCs->id, 'role' => Role::ProgramChair, 'program_id' => $bscs->id]);

        $adviserOne = $this->user('Adviser One', 'adviser-one@sdao.test');
        $computingSociety = Organization::create([
            'name' => 'Computing Society',
            'school_id' => $ccit->id,
            'program_id' => $bscs->id,
        ]);
        RoleAssignment::create(['user_id' => $adviserOne->id, 'role' => Role::Adviser, 'organization_id' => $computingSociety->id]);

        $studentAlpha = $this->user('Student Alpha', 'student-alpha@sdao.test');
        RoleAssignment::create(['user_id' => $studentAlpha->id, 'role' => Role::Student, 'organization_id' => $computingSociety->id]);

        // Program: BS Information Technology
        $bsit = Program::create(['school_id' => $ccit->id, 'name' => 'BS Information Technology']);
        $chairIt = $this->user('Chair IT', 'chair-it@sdao.test');
        RoleAssignment::create(['user_id' => $chairIt->id, 'role' => Role::ProgramChair, 'program_id' => $bsit->id]);

        $adviserTwo = $this->user('Adviser Two', 'adviser-two@sdao.test');
        $itGuild = Organization::create([
            'name' => 'IT Guild',
            'school_id' => $ccit->id,
            'program_id' => $bsit->id,
        ]);
        RoleAssignment::create(['user_id' => $adviserTwo->id, 'role' => Role::Adviser, 'organization_id' => $itGuild->id]);

        $studentBeta = $this->user('Student Beta', 'student-beta@sdao.test');
        RoleAssignment::create(['user_id' => $studentBeta->id, 'role' => Role::Student, 'organization_id' => $itGuild->id]);

        // ── Empty-shell regular schools (structure present, no people yet) ───

        School::create(['name' => 'School of Business and Accountancy', 'type' => 'regular']);
        School::create(['name' => 'School of Health Sciences', 'type' => 'regular']);

        // ── Senior High School ───────────────────────────────────────────────

        $shs = School::create(['name' => 'Senior High School', 'type' => 'senior_high']);
        $principal = $this->user('Principal SHS', 'principal-shs@sdao.test');
        RoleAssignment::create(['user_id' => $principal->id, 'role' => Role::Principal, 'school_id' => $shs->id]);

        $adviserShs = $this->user('Adviser SHS', 'adviser-shs@sdao.test');
        $shsCouncil = Organization::create([
            'name' => 'SHS Student Council',
            'school_id' => $shs->id,
            'program_id' => null,
        ]);
        RoleAssignment::create(['user_id' => $adviserShs->id, 'role' => Role::Adviser, 'organization_id' => $shsCouncil->id]);

        $studentGamma = $this->user('Student Gamma', 'student-gamma@sdao.test');
        RoleAssignment::create(['user_id' => $studentGamma->id, 'role' => Role::Student, 'organization_id' => $shsCouncil->id]);
    }

    private function user(string $name, string $email): User
    {
        return User::factory()->create(['name' => $name, 'email' => $email]);
    }
}
