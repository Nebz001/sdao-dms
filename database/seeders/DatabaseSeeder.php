<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * RealRosterSeeder (real, admin-provisioned staff) replaces IdentitySeeder
     * here. IdentitySeeder — along with MembershipSeeder, CalendarSeeder, and
     * ProposalSeeder, which all depend on IdentitySeeder's placeholder
     * students/organizations — is retained solely as the test fixture; the
     * real roster is staff-only and seeds no students or organizations.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);
        $this->call(RealRosterSeeder::class);
        $this->call(WorkflowTemplateSeeder::class);
    }
}
