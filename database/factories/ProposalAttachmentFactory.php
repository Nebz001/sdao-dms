<?php

namespace Database\Factories;

use App\Models\ProposalAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalAttachment>
 */
class ProposalAttachmentFactory extends Factory
{
    protected $model = ProposalAttachment::class;

    public function definition(): array
    {
        return [
            'original_filename' => $this->faker->word().'.pdf',
            'path' => 'proposals/'.$this->faker->uuid().'.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10000, 5000000),
        ];
    }
}
