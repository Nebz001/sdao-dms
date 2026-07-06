<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\User;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentStepApproval>
 */
class DocumentStepApprovalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'workflow_step_id' => WorkflowStep::factory(),
            'step_position' => 1,
            'user_id' => User::factory(),
        ];
    }
}
