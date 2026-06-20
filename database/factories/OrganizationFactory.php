<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Program;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $program = Program::factory()->create();

        return [
            'name' => fake()->words(3, true).' Society',
            'school_id' => $program->school_id,
            'program_id' => $program->id,
        ];
    }

    /**
     * An organization that belongs directly to Senior High School (no program).
     */
    public function seniorHigh(School $school): static
    {
        return $this->state([
            'school_id' => $school->id,
            'program_id' => null,
        ]);
    }
}
