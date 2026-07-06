<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\OrganizationRegistrationDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationRegistrationDetail>
 */
class OrganizationRegistrationDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'organization_type' => fake()->randomElement(OrganizationType::cases()),
            'description' => fake()->paragraph(),
            'contact_person' => fake()->name(),
            'contact_number' => fake()->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
            'date_organized' => fake()->date(),
            'adviser_id' => null,
            'roster' => [fake()->name(), fake()->name()],
        ];
    }
}
