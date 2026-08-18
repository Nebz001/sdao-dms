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
            'disk' => 'supabase',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10000, 5000000),
        ];
    }

    /**
     * A pre-migration attachment still sitting on the local disk — used to
     * cover backward compatibility with rows written before the Supabase
     * migration.
     */
    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'disk' => 'local',
        ]);
    }
}
