<?php

namespace Database\Factories;

use App\Models\AdviserTerm;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdviserTerm>
 */
class AdviserTermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'started_at' => now()->subMonths(2),
        ];
    }

    public function ended(): static
    {
        return $this->state(['ended_at' => now()->subMonth()]);
    }
}
