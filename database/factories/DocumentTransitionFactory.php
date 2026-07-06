<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTransition>
 */
class DocumentTransitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'actor_id' => null,
            'action' => TransitionAction::Submitted,
            'from_status' => null,
            'to_status' => DocumentStatus::InReview,
            'step_position' => null,
            'comment' => null,
        ];
    }
}
