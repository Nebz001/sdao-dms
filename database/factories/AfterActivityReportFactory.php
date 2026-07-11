<?php

namespace Database\Factories;

use App\Models\AfterActivityReport;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfterActivityReport>
 *
 * Note: `activity_proposal_id` has no default — callers must pass an existing
 * ActivityProposal id explicitly, since the parent must be a real,
 * already-Approved proposal for the hard link to be valid.
 */
class AfterActivityReportFactory extends Factory
{
    protected $model = AfterActivityReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'summary' => fake()->paragraphs(3, true),
            'outcomes' => fake()->paragraph(),
            'participant_count' => fake()->numberBetween(10, 200),
        ];
    }
}
