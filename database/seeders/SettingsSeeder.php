<?php

namespace Database\Seeders;

use App\Enums\Term;
use App\Support\CurrentTerm;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the global, admin-controlled settings (Phase 2 item 6: current term).
     */
    public function run(): void
    {
        CurrentTerm::set(Term::FirstTerm);
    }
}
