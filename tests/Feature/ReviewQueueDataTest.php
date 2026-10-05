<?php

use App\Approval\ApprovalEngine;
use App\Approval\ReviewQueueData;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('tierFor buckets days into fresh (0-2), aging (3-7) and overdue (8+)', function (int $days, string $tier) {
    expect(ReviewQueueData::tierFor($days))->toBe($tier);
})->with([[0, 'fresh'], [2, 'fresh'], [3, 'aging'], [7, 'aging'], [8, 'overdue'], [30, 'overdue']]);

test('queue rows carry the submitted date, waiting days and tier, oldest first', function () {
    $old = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->student);
    $new = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->student);
    DocumentTransition::where('document_id', $old->id)->update(['created_at' => Date::now()->subDays(12)]);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('queue', 2)
            ->where('queue.0.id', $old->id)
            ->where('queue.0.waiting_days', 12)
            ->where('queue.0.tier', 'overdue')
            ->where('queue.1.id', $new->id)
            ->where('queue.1.tier', 'fresh')
            ->where('extraColumnLabel', 'College')
            ->where('queue.0.college', $this->org->school?->name)
        );
});

test('stats and recent decisions count approved and returned documents, and only for people who can see them', function () {
    $approved = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->student);
    $this->engine->approve($approved, $this->sdaoA);
    $this->engine->approve($approved->refresh(), $this->sdaoB);

    $returned = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->student);
    $this->engine->returnForRevision($returned, $this->sdaoA, 'Fix the adviser.');

    expect($approved->refresh()->status)->toBe(DocumentStatus::Approved);

    $queue = new ReviewQueueData(FormType::OrganizationRegistration, 'review.registrations.show', fn (Document $d) => null);

    $stats = $queue->stats($this->sdaoA);
    expect($stats['submitted']['total'])->toBe(2)
        ->and($stats['submitted']['thisWeek'])->toBe(2)
        ->and($stats['decided'])->toBe(['approved' => 1, 'returned' => 1, 'rejected' => 0, 'total' => 2]);

    $recent = $queue->recent($this->sdaoA);
    expect($recent)->toHaveCount(2)
        ->and(collect($recent)->pluck('result')->sort()->values()->all())->toBe(['approved', 'returned'])
        ->and($recent[0]['organization'])->toBe($this->org->name)
        ->and($recent[0]['college'])->toBe($this->org->school?->name);

    // A student with no approver role sees no one else's activity.
    $outsider = User::factory()->create();
    expect($queue->recent($outsider))->toBe([])
        ->and($queue->stats($outsider)['decided']['total'])->toBe(0);
});

test('the deferred props resolve on the index page', function () {
    $doc = shortChainInReviewDoc(FormType::AfterActivityReport, $this->org, $this->engine, $this->student);
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Add photos.');

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('stats')
            ->reloadOnly('stats', fn ($stats) => $stats->where('stats.decided.returned', 1)->etc())
            ->reloadOnly('recent', fn ($recent) => $recent->has('recent', 1)->etc())
        );
});
