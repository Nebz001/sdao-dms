<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleAssignment>
 */
class RoleAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => Role::Student,
            'school_id' => null,
            'program_id' => null,
            'organization_id' => null,
        ];
    }
}
