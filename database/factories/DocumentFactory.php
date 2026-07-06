<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_type' => FormType::OrganizationRegistration,
            'variant' => null,
            'title' => fake()->sentence(4),
            'status' => DocumentStatus::Draft,
            'current_step_position' => null,
            'organization_id' => Organization::factory(),
            'workflow_template_id' => null,
            'submitted_by' => null,
        ];
    }
}
