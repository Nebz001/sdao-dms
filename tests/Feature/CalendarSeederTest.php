<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use Database\Seeders\CalendarSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Regression coverage for the seeder pipeline itself. No other test seeds
 * CalendarSeeder — it's easy for a signature change on SubmitActivityCalendar
 * (e.g. Phase 2 item 6 dropping the `term` param) to break this seeder
 * silently, since the ~350-test suite otherwise never runs it.
 */
test('CalendarSeeder runs without error and seeds the expected calendars', function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class, CalendarSeeder::class]);

    $approvedCount = Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('status', DocumentStatus::Approved->value)
        ->count();
    $inReviewCount = Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('status', DocumentStatus::InReview->value)
        ->count();

    expect($approvedCount)->toBe(1);
    expect($inReviewCount)->toBe(1);
});
