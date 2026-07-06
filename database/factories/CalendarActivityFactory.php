<?php

namespace Database\Factories;

use App\Models\CalendarActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarActivity>
 */
class CalendarActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->time('H:i', '18:00');
        // end is start + 1–3 hours (capped at 23:00)
        $startTs = strtotime("2000-01-01 {$start}");
        $endTs = min($startTs + fake()->numberBetween(3600, 10800), strtotime('2000-01-01 23:00'));

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'venue' => fake()->randomElement(['Auditorium', 'AVR 1', 'Gymnasium', 'Function Hall', 'Room 201']),
            'activity_date' => fake()->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'start_time' => date('H:i', $startTs),
            'end_time' => date('H:i', $endTs),
        ];
    }
}
