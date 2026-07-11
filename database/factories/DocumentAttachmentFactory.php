<?php

namespace Database\Factories;

use App\Models\DocumentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAttachment>
 */
class DocumentAttachmentFactory extends Factory
{
    protected $model = DocumentAttachment::class;

    public function definition(): array
    {
        return [
            'slot_key' => $this->faker->word(),
            'original_filename' => $this->faker->word().'.pdf',
            'path' => 'attachments/'.$this->faker->uuid().'.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10000, 5000000),
        ];
    }
}
