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
     * RealRosterSeeder (real, admin-provisioned staff) is the base. The demo
     * restore sequence adds IdentitySeeder (the
     * adviser pool) and then demo:reset. Test-only fixtures (placeholder
     * SDAO accounts, schools, students, organizations) live under tests/Fixtures and are
     * never seeded here.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);
        $this->call(RealRosterSeeder::class);
        $this->call(WorkflowTemplateSeeder::class);
    }
}
