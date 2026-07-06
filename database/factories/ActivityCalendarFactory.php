<?php

namespace Database\Factories;

use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityCalendar>
 */
class ActivityCalendarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year' => AcademicYear::current(),
            'term' => fake()->randomElement(Term::cases()),
        ];
    }
}
