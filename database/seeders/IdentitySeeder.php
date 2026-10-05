<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The accounts a dev or demo database needs on top of the real roster
 * (RealRosterSeeder owns every school, program, dean, chair, principal and
 * director): the two SDAO sign-in accounts, and the unassigned adviser pool
 * DemoDataSeeder picks from when it founds organizations.
 *
 * It creates no school, program, organization, student or director of its
 * own. Everything else the test suite needs lives under tests/Fixtures, so
 * none of it can reach a dev or demo database.
 *
 * Every account uses the password "ict@1234", the same convention as
 * RealRosterSeeder and DemoDataSeeder. `user()` must set it explicitly:
 * leaving it to UserFactory's default silently gives the account the
 * password "password" instead.
 */
class IdentitySeeder extends Seeder
{
    use WithoutModelEvents;

    private const string PASSWORD = 'ict@1234';

    public function run(): void
    {
        foreach ([['SDAO Member A', 'sdao-a@nu-lipa.edu.ph'], ['SDAO Member B', 'sdao-b@nu-lipa.edu.ph']] as [$name, $email]) {
            RoleAssignment::create(['user_id' => $this->user($name, $email)->id, 'role' => Role::SdaoMember]);
        }

        // The demo adviser pool: provisioned with NO organization. They own no
        // school, program or organization; DemoDataSeeder binds them as the
        // advisers of the organizations it founds.
        foreach ([
            ['Adviser One', 'adviser-one@nu-lipa.edu.ph'],
            ['Adviser Two', 'adviser-two@nu-lipa.edu.ph'],
            ['Adviser SHS', 'adviser-shs@nu-lipa.edu.ph'],
        ] as [$name, $email]) {
            RoleAssignment::create(['user_id' => $this->user($name, $email)->id, 'role' => Role::Adviser]);
        }
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
