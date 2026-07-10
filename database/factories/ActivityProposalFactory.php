<?php

namespace Database\Factories;

use App\Enums\ProposalCalendarMode;
use App\Models\ActivityProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityProposal>
 */
class ActivityProposalFactory extends Factory
{
    protected $model = ActivityProposal::class;

    public function definition(): array
    {
        return [
            'calendar_mode' => $this->faker->randomElement(ProposalCalendarMode::cases())->value,
            'calendar_activity_id' => null,
            'title' => $this->faker->sentence(4),
            'objectives' => null,
            'narrative' => null,
            'proposed_budget' => null,
            'form_step' => 2,
        ];
    }
}
