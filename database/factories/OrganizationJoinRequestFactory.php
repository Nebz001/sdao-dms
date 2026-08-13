<?php

namespace Database\Factories;

use App\Enums\JoinRequestStatus;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationJoinRequest>
 */
class OrganizationJoinRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'status' => JoinRequestStatus::Pending,
            'decided_by' => null,
            'decided_at' => null,
            'decision_comment' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'status' => JoinRequestStatus::Approved,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state([
            'status' => JoinRequestStatus::Declined,
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ]);
    }
}
