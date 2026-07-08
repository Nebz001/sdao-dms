<?php

namespace Database\Factories;

use App\Models\AfterActivityReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AfterActivityReportAttachment>
 */
class AfterActivityReportAttachmentFactory extends Factory
{
    protected $model = AfterActivityReportAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'original_filename' => $this->faker->word().'.pdf',
            'path' => 'reports/'.$this->faker->uuid().'.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size' => $this->faker->numberBetween(10000, 5000000),
        ];
    }
}
