<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentRemark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRemark>
 */
class DocumentRemarkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'created_at' => now(),
        ];
    }
}
