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
            'purpose_of_organization' => fake()->paragraph(),
            'contact_person' => fake()->name(),
            'contact_no' => fake()->phoneNumber(),
            'email_address' => fake()->safeEmail(),
            'date_organized' => fake()->date(),
            'adviser_id' => null,
            'roster' => [fake()->name(), fake()->name()],
            'academic_year' => null,
        ];
    }
}
