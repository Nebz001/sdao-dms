<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentReminder>
 */
class DocumentReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'sent_by' => null,
            'recipient_count' => 1,
            'created_at' => now(),
        ];
    }
}
