<?php

use App\ActivityProposals\ResubmitActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Calendar\UpdateActivityCalendar;
use App\Enums\ProposalCalendarMode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Regression: an approver opening (or the 5s poller reloading) a review page
 * for a document that was Returned and then resubmitted must get a 200, not
 * a crash.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('the proposal review page and its poll reload return 200 after a return and resubmit', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OffCalendar,
        data: ['title' => 'Coding Night', 'venue' => 'Function Hall', 'activity_date' => '2026-12-10', 'start_time' => '09:00', 'end_time' => '11:00'],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(actor: $student, document: $draft, objectives: 'Goal');

    app(ApprovalEngine::class)->returnForRevision($doc, $adviser, 'Fix it.', ['schedule_venue', 'budget'], ['budget' => 'Too high']);
    $doc->refresh();

    app(ResubmitActivityProposal::class)->execute($student, $doc, [
        'objectives' => 'Goal',
        'activity_description' => 'Description',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Flow',
        'expense_items' => [['material' => 'Venue', 'quantity' => '1', 'unit_price' => '5000']],
        'responsible_persons' => ['Person'],
        'proposed_budget' => '5000',
        'title' => 'Coding Night',
        'venue' => 'Main Gymnasium',
        'activity_date' => '2026-12-10',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    DB::enableQueryLog();
    $this->actingAs($adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();
    // Guards the page against N+1 growth: latency to a remote Postgres multiplies
    // every query, and this page is re-requested by the 5s poller.
    expect(count(DB::getQueryLog()))->toBeLessThan(80);

    // Exactly what use-document-updates.ts requests every 5 seconds.
    $this->actingAs($adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'review/activity-proposals/show',
            'X-Inertia-Partial-Data' => 'document,proposal,activity,attachments,view,hasApproved,canAct,activityConflict,hasConfirmedConflict',
        ])
        ->assertOk();
});

test('the calendar review page returns 200 after repeated returns and resubmits', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();

    ['document' => $doc] = app(SubmitActivityCalendar::class)->execute(
        actor: $student,
        organization: $org,
        activities: [
            ['name' => 'Orientation', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
            ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '17:00'],
        ],
    );

    foreach ([1, 2, 3] as $round) {
        app(ApprovalEngine::class)->returnForRevision($doc, $sdao, 'Fix.', ['activity_0', 'activity_1'], ['activity_0' => 'Note']);
        $doc->refresh();
        app(UpdateActivityCalendar::class)->execute(
            actor: $student,
            document: $doc,
            activities: [
                ['name' => 'Orientation', 'venue' => "Auditorium {$round}", 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
                ['name' => 'Hackathon', 'venue' => 'AVR 1', 'activity_date' => '2026-10-01', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
        );
        $doc->refresh();
    }

    $this->actingAs($sdao)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertOk();
});

/**
 * Production 502 (nginx: "upstream sent too big header while reading response
 * header from upstream") on a full load of /review/activity-proposals/{id}.
 * Laravel's AddLinkHeadersForPreloadedAssets middleware put one Link header
 * entry per preloaded chunk (~2.7 KB for this page) on top of cookies, which
 * crowded nginx's default 4 KB FastCGI header buffer. The <link rel="modulepreload">
 * tags @vite already writes into the HTML do the same job, so no Link header
 * is sent and the header block stays far from the limit.
 */
test('a full load of the review page keeps its response headers well under nginx\'s 4 KB buffer', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OffCalendar,
        data: ['title' => 'Coding Night', 'venue' => 'Function Hall', 'activity_date' => '2026-12-10', 'start_time' => '09:00', 'end_time' => '11:00'],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(actor: $student, document: $draft, objectives: 'Goal');

    $response = $this->actingAs($adviser)->get(route('review.activity-proposals.show', $doc))->assertOk();

    $response->assertSee('rel="modulepreload"', false);
    expect($response->headers->has('Link'))->toBeFalse();
    expect(strlen((string) $response->headers))->toBeLessThan(2048);
})->skip(fn () => ! file_exists(public_path('build/manifest.json')), 'needs a built Vite manifest');
