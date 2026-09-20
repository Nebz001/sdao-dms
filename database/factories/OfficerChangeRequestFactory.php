<?php

namespace Database\Factories;

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficerChangeRequest>
 */
class OfficerChangeRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'requested_by' => User::factory(),
            'position' => fake()->randomElement(OfficerPosition::cases()),
            'nominee_id' => User::factory(),
            'outgoing_user_id' => null,
            'reason' => null,
            'status' => OfficerChangeRequestStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_comment' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => OfficerChangeRequestStatus::Approved,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state([
            'status' => OfficerChangeRequestStatus::Declined,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
