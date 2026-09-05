<?php

namespace Database\Seeders;

use App\Enums\Term;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the global, admin-controlled current period setting.
     */
    public function run(): void
    {
        CurrentPeriod::set(new AcademicPeriod(AcademicYear::forDate(Date::now()), Term::FirstTerm));
    }
}
