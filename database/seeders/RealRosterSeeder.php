<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the real, admin-provisioned staff roster for the running application
 * (the actual named individuals — replaces the placeholder IdentitySeeder,
 * which is retained solely as the test fixture).
 *
 * Scope is STAFF/APPROVERS ONLY: no students, no organizations, no demo
 * documents. Students self-register and advisers bind organizations at
 * registration approval, so those are created through the real flow — not
 * seeded. Marvin Atanacio (the sole adviser) is therefore seeded UNBOUND
 * (organization_id null); he is bound to an org only when a registration
 * naming him is Approved (invariant #5).
 *
 * Email convention: honorifics stripped, lastname + first-name initial +
 * explicit middle initial (if the name gives one) + @sdao.test, lowercase,
 * ASCII (ñ → n). Every account uses the password "ict@1234".
 */
class RealRosterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Global approvers (unscoped) ──────────────────────────────────────
        $magpantay = $this->user('Carl Justin Magpantay', 'magpantayc@sdao.test');
        $enayo = $this->user('Zaira Joy Enayo', 'enayoz@sdao.test');
        RoleAssignment::create(['user_id' => $magpantay->id, 'role' => Role::SdaoMember]);
        RoleAssignment::create(['user_id' => $enayo->id, 'role' => Role::SdaoMember]);

        $quizon = $this->user('Pia Jasmin I. Quizon', 'quizonpi@sdao.test');
        RoleAssignment::create(['user_id' => $quizon->id, 'role' => Role::AssistantDirectorAcademicServices]);

        $fabito = $this->user('Bernie S. Fabito', 'fabitobs@sdao.test');
        RoleAssignment::create(['user_id' => $fabito->id, 'role' => Role::AcademicDirector]);

        $palupit = $this->user('Avelino D. Palupit', 'palupitad@sdao.test');
        RoleAssignment::create(['user_id' => $palupit->id, 'role' => Role::ExecutiveDirector]);

        // ── Senior High School (no programs, no dean — a single principal) ───
        $shs = School::create(['name' => 'Senior High School', 'type' => 'senior_high']);
        $rosario = $this->user('Erna Rosario', 'rosarioe@sdao.test');
        RoleAssignment::create(['user_id' => $rosario->id, 'role' => Role::Principal, 'school_id' => $shs->id]);

        // ── SACE — School of Architecture, Computing, and Engineering ────────
        $sace = School::create(['name' => 'School of Architecture, Computing, and Engineering', 'type' => 'regular']);
        $this->dean('Carolyn D. Matira', 'matiracd@sdao.test', $sace);
        $this->chair('Dr. Alice Lacorte', 'lacortea@sdao.test', $this->program($sace, 'BS Computer Science'));
        $this->chair('Sir Joseph Michael E. Aramil', 'aramilje@sdao.test', $this->program($sace, 'BS Information Technology'));
        $this->chair('Engr. Emmanuel P. Maala', 'maalaep@sdao.test', $this->program($sace, 'BS Civil Engineering'));
        $this->chair('Ar. Ryan Panapanaan', 'panapanaanr@sdao.test', $this->program($sace, 'BS Architecture'));

        // ── SAHS — School of Allied Health and Sciences ─────────────────────
        $sahs = School::create(['name' => 'School of Allied Health and Sciences', 'type' => 'regular']);
        $this->dean('Maria Lourdes C. Bañaga', 'banagamc@sdao.test', $sahs);
        $this->chair('Dr. Maria Andrea M. Magaling', 'magalingmm@sdao.test', $this->program($sahs, 'BS Nursing'));
        $this->chair('Ms. Diane Angelika Nicole D. Novicio', 'noviciodd@sdao.test', $this->program($sahs, 'BS Psychology'));

        // Medical Technology program exists, but its "Associate Dean" is NOT a
        // step in any workflow chain and there is no AssociateDean role — so
        // Evangelista is seeded as a user only, with her title carried inline
        // in her display name, and no RoleAssignment.
        $this->program($sahs, 'Medical Technology');
        $this->user('Maria Dolores C. Evangelista (Associate Dean, Medical Technology)', 'evangelistamc@sdao.test');

        // ── SABM — School of Accountancy, Business, and Management ───────────
        $sabm = School::create(['name' => 'School of Accountancy, Business, and Management', 'type' => 'regular']);
        $this->dean('Jay-Ar C. Dimaculangan', 'dimaculanganjc@sdao.test', $sabm);

        // Ronald Catapang chairs BOTH BSBA programs — one user, two program-scoped assignments.
        $catapang = $this->user('Dr. Ronald Catapang', 'catapangr@sdao.test');
        RoleAssignment::create(['user_id' => $catapang->id, 'role' => Role::ProgramChair, 'program_id' => $this->program($sabm, 'BS Business Administration (Financial Management)')->id]);
        RoleAssignment::create(['user_id' => $catapang->id, 'role' => Role::ProgramChair, 'program_id' => $this->program($sabm, 'BS Business Administration (Marketing Management)')->id]);

        $this->chair('Engr. Rosa Maria C. Cayabyab', 'cayabyabrc@sdao.test', $this->program($sabm, 'BS Accountancy'));
        $this->chair('Dr. Gene Roy P. Hernandez', 'hernandezgp@sdao.test', $this->program($sabm, 'BS Tourism Management'));

        // ── Adviser (unbound — bound to an org only at registration approval) ─
        $atanacio = $this->user('Marvin Atanacio', 'atanaciom@sdao.test');
        RoleAssignment::create(['user_id' => $atanacio->id, 'role' => Role::Adviser, 'organization_id' => null]);
    }

    private function program(School $school, string $name): Program
    {
        return Program::create(['school_id' => $school->id, 'name' => $name]);
    }

    private function dean(string $name, string $email, School $school): void
    {
        $user = $this->user($name, $email);
        RoleAssignment::create(['user_id' => $user->id, 'role' => Role::Dean, 'school_id' => $school->id]);
    }

    private function chair(string $name, string $email, Program $program): void
    {
        $user = $this->user($name, $email);
        RoleAssignment::create(['user_id' => $user->id, 'role' => Role::ProgramChair, 'program_id' => $program->id]);
    }

    private function user(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('ict@1234'),
        ]);
    }
}
