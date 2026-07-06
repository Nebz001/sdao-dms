<?php

namespace Database\Factories;

use App\Enums\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'position' => fake()->randomElement(OfficerPosition::cases()),
            'academic_year' => AcademicYear::current(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function president(): static
    {
        return $this->state(['position' => OfficerPosition::President]);
    }

    public function secretary(): static
    {
        return $this->state(['position' => OfficerPosition::Secretary]);
    }
}
