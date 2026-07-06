<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_template_id' => WorkflowTemplate::factory(),
            'position' => 1,
            'role' => Role::Adviser,
            'required_approvals' => 1,
        ];
    }
}
