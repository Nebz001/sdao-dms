<?php

namespace Database\Factories;

use App\Enums\FormType;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_type' => FormType::OrganizationRegistration,
            'variant' => null,
            'name' => fake()->words(3, true),
        ];
    }
}
