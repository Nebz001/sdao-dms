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
 * explicit middle initial (if the name gives one) + @nu-lipa.edu.ph, lowercase,
 * ASCII (ñ → n). Every account uses the password "ict@1234".
 */
class RealRosterSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── Global approvers (unscoped) ──────────────────────────────────────
        $magpantay = $this->user('Carl Justin Magpantay', 'magpantayc@nu-lipa.edu.ph');
        $enayo = $this->user('Zaira Joy Enayo', 'enayoz@nu-lipa.edu.ph');
        $this->assignRole($magpantay, Role::SdaoMember);
        $this->assignRole($enayo, Role::SdaoMember);

        $quizon = $this->user('Pia Jasmin I. Quizon', 'quizonpi@nu-lipa.edu.ph');
        $this->assignRole($quizon, Role::AssistantDirectorAcademicServices);

        $fabito = $this->user('Bernie S. Fabito', 'fabitobs@nu-lipa.edu.ph');
        $this->assignRole($fabito, Role::AcademicDirector);

        $palupit = $this->user('Avelino D. Palupit', 'palupitad@nu-lipa.edu.ph');
        $this->assignRole($palupit, Role::ExecutiveDirector);

        // ── Senior High School (no programs, no dean — a single principal) ───
        $shs = School::firstOrCreate(['name' => 'Senior High School'], ['type' => 'senior_high']);
        $rosario = $this->user('Erna Rosario', 'rosarioe@nu-lipa.edu.ph');
        $this->assignRole($rosario, Role::Principal, school: $shs);

        // ── SACE — School of Architecture, Computing, and Engineering ────────
        $sace = School::firstOrCreate(['name' => 'School of Architecture, Computing, and Engineering'], ['type' => 'regular']);
        $this->dean('Carolyn D. Matira', 'matiracd@nu-lipa.edu.ph', $sace);
        $this->chair('Dr. Alice Lacorte', 'lacortea@nu-lipa.edu.ph', $this->program($sace, 'BS Computer Science'));
        $this->chair('Sir Joseph Michael E. Aramil', 'aramilje@nu-lipa.edu.ph', $this->program($sace, 'BS Information Technology'));
        $this->chair('Engr. Emmanuel P. Maala', 'maalaep@nu-lipa.edu.ph', $this->program($sace, 'BS Civil Engineering'));
        $this->chair('Ar. Ryan Panapanaan', 'panapanaanr@nu-lipa.edu.ph', $this->program($sace, 'BS Architecture'));

        // ── SAHS — School of Allied Health and Sciences ─────────────────────
        $sahs = School::firstOrCreate(['name' => 'School of Allied Health and Sciences'], ['type' => 'regular']);
        $this->dean('Maria Lourdes C. Bañaga', 'banagamc@nu-lipa.edu.ph', $sahs);
        $this->chair('Dr. Maria Andrea M. Magaling', 'magalingmm@nu-lipa.edu.ph', $this->program($sahs, 'BS Nursing'));
        $this->chair('Ms. Diane Angelika Nicole D. Novicio', 'noviciodd@nu-lipa.edu.ph', $this->program($sahs, 'BS Psychology'));

        // Medical Technology program exists, but its "Associate Dean" is NOT a
        // step in any workflow chain and there is no AssociateDean role — so
        // Evangelista is seeded as a user only, with her title carried inline
        // in her display name, and no RoleAssignment.
        $this->program($sahs, 'Medical Technology');
        $this->user('Maria Dolores C. Evangelista (Associate Dean, Medical Technology)', 'evangelistamc@nu-lipa.edu.ph');

        // ── SABM — School of Accountancy, Business, and Management ───────────
        $sabm = School::firstOrCreate(['name' => 'School of Accountancy, Business, and Management'], ['type' => 'regular']);
        $this->dean('Jay-Ar C. Dimaculangan', 'dimaculanganjc@nu-lipa.edu.ph', $sabm);

        // Ronald Catapang chairs BOTH BSBA programs — one user, two program-scoped assignments.
        $catapang = $this->user('Dr. Ronald Catapang', 'catapangr@nu-lipa.edu.ph');
        $this->assignRole($catapang, Role::ProgramChair, program: $this->program($sabm, 'BS Business Administration (Financial Management)'));
        $this->assignRole($catapang, Role::ProgramChair, program: $this->program($sabm, 'BS Business Administration (Marketing Management)'));

        $this->chair('Engr. Rosa Maria C. Cayabyab', 'cayabyabrc@nu-lipa.edu.ph', $this->program($sabm, 'BS Accountancy'));
        $this->chair('Dr. Gene Roy P. Hernandez', 'hernandezgp@nu-lipa.edu.ph', $this->program($sabm, 'BS Tourism Management'));

        // ── Adviser (unbound — bound to an org only at registration approval) ─
        $atanacio = $this->user('Marvin Atanacio', 'atanaciom@nu-lipa.edu.ph');
        $this->assignRole($atanacio, Role::Adviser);
    }

    private function program(School $school, string $name): Program
    {
        return Program::firstOrCreate(['school_id' => $school->id, 'name' => $name]);
    }

    private function dean(string $name, string $email, School $school): void
    {
        $user = $this->user($name, $email);
        $this->assignRole($user, Role::Dean, school: $school);
    }

    private function chair(string $name, string $email, Program $program): void
    {
        $user = $this->user($name, $email);
        $this->assignRole($user, Role::ProgramChair, program: $program);
    }

    /**
     * role_assignments has no unique constraint, so a plain create() would
     * duplicate on every rerun — firstOrCreate keyed on the full attribute
     * set makes reruns a no-op once an assignment already exists.
     */
    private function assignRole(User $user, Role $role, ?School $school = null, ?Program $program = null, ?Organization $organization = null): void
    {
        RoleAssignment::firstOrCreate([
            'user_id' => $user->id,
            'role' => $role,
            'school_id' => $school?->id,
            'program_id' => $program?->id,
            'organization_id' => $organization?->id,
        ]);
    }

    private function user(string $name, string $email): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('ict@1234'),
            ],
        );
    }
}
