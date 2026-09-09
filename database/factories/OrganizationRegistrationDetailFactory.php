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
            // organization_type is derived from the org's school_id in real
            // application flow (structural fix, 2026-09-09 plan) — the
            // factory has no resolved Organization to derive from at
            // definition time, so it defaults to Co-Curricular. Tests that
            // care about the Extra-Curricular shape must override this
            // explicitly to match whatever org they attach the detail to.
            'organization_type' => OrganizationType::CoCurricular,
            'purpose_of_organization' => fake()->paragraph(),
            'contact_person' => fake()->name(),
            'contact_no' => fake()->phoneNumber(),
            'email_address' => fake()->safeEmail(),
            'date_organized' => fake()->date(),
            'adviser_id' => null,
            'academic_year' => null,
        ];
    }
}
