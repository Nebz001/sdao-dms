<?php

namespace Database\Seeders;

use App\Enums\OfficerPosition;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Binds the seeded students as active officers of their organizations.
 * Must run after IdentitySeeder (depends on the seeded users and orgs).
 *
 * Memberships created:
 *   - Student Alpha  → President, Computing Society
 *   - Student Delta  → Secretary, Computing Society  (new account — exercises equal-partner rule)
 *   - Student Beta   → President, IT Guild
 *   - Student Gamma  → President, SHS Student Council
 */
class MembershipSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $academicYear = AcademicYear::current();

        $computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
        $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
        $shsCouncil = Organization::where('name', 'SHS Student Council')->firstOrFail();

        $studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
        $studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail();
        $studentGamma = User::where('email', 'student-gamma@sdao.test')->firstOrFail();

        // Extra student: Secretary of Computing Society (exercises equal-partner rule).
        $studentDelta = User::factory()->create([
            'name' => 'Student Delta',
            'email' => 'student-delta@sdao.test',
        ]);
        RoleAssignment::create([
            'user_id' => $studentDelta->id,
            'role' => Role::Student,
            'organization_id' => $computingSociety->id,
        ]);

        $this->bind($studentAlpha, $computingSociety, OfficerPosition::President, $academicYear);
        $this->bind($studentDelta, $computingSociety, OfficerPosition::Secretary, $academicYear);
        $this->bind($studentBeta, $itGuild, OfficerPosition::President, $academicYear);
        $this->bind($studentGamma, $shsCouncil, OfficerPosition::President, $academicYear);
    }

    private function bind(User $student, Organization $org, OfficerPosition $position, string $academicYear): void
    {
        OrganizationMembership::create([
            'user_id' => $student->id,
            'organization_id' => $org->id,
            'position' => $position->value,
            'academic_year' => $academicYear,
            'is_active' => true,
        ]);
    }
}
