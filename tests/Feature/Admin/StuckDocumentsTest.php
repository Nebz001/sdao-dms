<?php

use App\Dashboard\StuckDocumentStats;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Notifications\StuckDocumentReminderNotification;
use App\Organizations\OrganizationMembershipService;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The Stuck Documents page: the four cards (always the whole stuck set), the
 * idle buckets, and the Remind action. A document is "stuck" when it is In
 * Review or Returned (the existing definition); idle time runs from its
 * latest transition.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->travelTo('2026-09-16 12:00:00');
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * A stuck document whose only transition is `$idleDays` old. An in-review
 * proposal sits at `$position` of its chain (1 = adviser); a registration has
 * only the SDAO step. A returned document keeps its position.
 */
function stuckDoc(
    Organization $org,
    int $idleDays,
    DocumentStatus $status = DocumentStatus::InReview,
    int $position = 1,
    FormType $formType = FormType::ActivityProposal,
    int $extraHours = 0,
    ?User $submitter = null,
): Document {
    $template = $formType === FormType::ActivityProposal
        ? WorkflowTemplate::where('form_type', $formType)->where('variant', ProposalVariant::RegularOnCalendar)->firstOrFail()
        : WorkflowTemplate::where('form_type', $formType)->whereNull('variant')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => $formType,
        'variant' => $formType === FormType::ActivityProposal ? ProposalVariant::RegularOnCalendar : null,
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
        'status' => $status,
        'current_step_position' => $position,
        'title' => 'Stuck Doc',
        'submitted_by' => $submitter?->id,
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $submitter?->id,
        'action' => $status === DocumentStatus::Returned ? TransitionAction::Returned : TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => $status,
        'step_position' => $position,
        'created_at' => now()->subDays($idleDays)->subHours($extraHours),
    ]);

    return $doc;
}

function stuckPage(mixed $test, array $query = []): TestResponse
{
    return $test->actingAs($test->sdaoA)->get(route('admin.stuck-documents.index', $query));
}

test('stuck right now counts in-review and returned documents and how many are over 30 days', function () {
    stuckDoc($this->org, 2);
    stuckDoc($this->org, 8);
    stuckDoc($this->itGuild, 20);
    stuckDoc($this->itGuild, 45);
    stuckDoc($this->org, 31, DocumentStatus::Returned);
    stuckDoc($this->org, 5, DocumentStatus::Approved);
    stuckDoc($this->org, 5, DocumentStatus::Draft);

    stuckPage($this)->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.total', 5)
        ->where('stats.withApprovers', 4)
        ->where('stats.returned', 1)
        ->where('stats.overThirty', 2)
    );
});

test('the idle buckets and median cover every stuck document', function () {
    foreach ([2, 8, 20, 45] as $days) {
        stuckDoc($this->org, $days);
    }
    stuckDoc($this->org, 31, DocumentStatus::Returned);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('stats.buckets', ['under_7' => 1, '7_14' => 1, '15_30' => 1, 'over_30' => 2])
        ->where('stats.medianDays', 20) // 2, 8, 20, 31, 45
    );
});

test('bucket and tone edges', function (int $days, string $bucket, string $tone) {
    expect(StuckDocumentStats::bucketFor($days))->toBe($bucket)
        ->and(StuckDocumentStats::toneFor($days))->toBe($tone);
})->with([
    [0, 'under_7', 'neutral'],
    [6, 'under_7', 'neutral'],
    [7, '7_14', 'warning'],
    [14, '7_14', 'warning'],
    [15, '15_30', 'warning'],
    [30, '15_30', 'warning'],
    [31, 'over_30', 'destructive'],
]);

test('the median of an even count rounds the middle pair', function () {
    foreach ([2, 9] as $days) {
        stuckDoc($this->org, $days);
    }

    stuckPage($this)->assertInertia(fn ($page) => $page->where('stats.medianDays', 6)); // (2 + 9) / 2 = 5.5 → 6
});

test('idle the longest names the organization, the holder and the date', function () {
    stuckDoc($this->org, 10);
    $oldest = stuckDoc($this->itGuild, 40, DocumentStatus::Returned);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('stats.idleLongest.organization', 'IT Guild')
        ->where('stats.idleLongest.waitingOn', 'The organization')
        ->where('stats.idleLongest.idleDays', 40)
        ->where('stats.idleLongest.tone', 'destructive')
        ->where('stats.idleLongest.sinceDate', '8/7/2026')
        ->where('stats.idleLongest.href', route('review.activity-proposals.show', $oldest))
    );
});

test('idle the longest breaks a tie by the earlier hand-off, then the lower id', function () {
    $later = stuckDoc($this->org, 40);
    $earlier = stuckDoc($this->itGuild, 40, extraHours: 5); // same whole days, but older

    stuckPage($this)->assertInertia(fn ($page) => $page->where('stats.idleLongest.organization', 'IT Guild'));
});

test('the cards are calm with nothing stuck', function () {
    stuckPage($this)->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats.total', 0)
        ->where('stats.overThirty', 0)
        ->where('stats.idleLongest', null)
        ->where('stats.holdingMost', null)
        ->where('stats.medianDays', 0)
    );
});

test('idle is measured from the latest transition, not updated_at', function () {
    $doc = stuckDoc($this->org, 50);
    DocumentTransition::create([
        'document_id' => $doc->id, 'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Resubmitted,
        'from_status' => DocumentStatus::Returned, 'to_status' => DocumentStatus::InReview,
        'step_position' => 1, 'created_at' => now()->subDays(3),
    ]);
    // updated_at says 50 days; it must be ignored.
    Document::whereKey($doc->id)->update(['updated_at' => now()->subDays(50)]);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('documents.data.0.idleDays', 3)
        ->where('documents.data.0.sinceDate', '9/13/2026')
        ->where('stats.buckets.under_7', 1)
        ->where('stats.overThirty', 0)
    );
});

test('holding the most counts documents per approver, with their role and longest wait', function () {
    stuckDoc($this->org, 5);                              // adviser-one, 5 days
    stuckDoc($this->org, 9);                              // adviser-one, 9 days
    stuckDoc($this->itGuild, 3);                          // adviser-two
    stuckDoc($this->org, 40, DocumentStatus::Returned);   // returned: held by the organization, not an approver

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('stats.holdingMost', [
            'name' => $this->adviserOne->name,
            'role' => 'Adviser',
            'stuck' => 2,
            'longestDays' => 9,
        ])
    );
});

test('holding the most breaks a tie by the longest wait, then by name', function () {
    // SDAO step (registration chain): 2 documents, longest 10. Adviser-one: 2 documents, longest 6.
    stuckDoc($this->org, 10, formType: FormType::OrganizationRegistration);
    stuckDoc($this->itGuild, 3, formType: FormType::OrganizationRegistration);
    stuckDoc($this->org, 6);
    stuckDoc($this->org, 2);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('stats.holdingMost.name', 'SDAO Office')
        ->where('stats.holdingMost.stuck', 2)
        ->where('stats.holdingMost.longestDays', 10)
    );

    // Equal count and equal longest wait: the name that sorts first wins.
    stuckDoc($this->org, 10);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('stats.holdingMost.stuck', 3)
        ->where('stats.holdingMost.name', $this->adviserOne->name)
    );
});

test('the cards ignore the filters', function () {
    stuckDoc($this->org, 3);
    stuckDoc($this->itGuild, 40, DocumentStatus::Returned);

    stuckPage($this, ['waiting_on' => 'org', 'search' => 'nothing matches'])->assertInertia(fn ($page) => $page
        ->where('documents.meta.total', 0)
        ->where('stats.total', 2)
        ->where('stats.buckets.over_30', 1)
    );
});

test('the idle filter matches the card buckets', function (string $bucket, int $expectedDays) {
    foreach ([3, 9, 20, 45] as $days) {
        stuckDoc($this->org, $days);
    }

    stuckPage($this, ['idle' => $bucket])->assertInertia(fn ($page) => $page
        ->where('documents.meta.total', 1)
        ->where('documents.data.0.idleDays', $expectedDays)
        ->where('filters.idle', $bucket)
    );
})->with([
    'under 7' => ['under_7', 3],
    '7 to 14' => ['7_14', 9],
    '15 to 30' => ['15_30', 20],
    'over 30' => ['over_30', 45],
]);

test('rows carry the badge fields, the bare title, the college and who the document waits on', function () {
    $this->itGuild->forceFill(['school_id' => null, 'program_id' => null])->save();
    $inReview = stuckDoc($this->org, 4);
    $returned = stuckDoc($this->itGuild, 12, DocumentStatus::Returned);

    stuckPage($this)->assertInertia(fn ($page) => $page
        ->where('documents.data', function ($rows) use ($inReview, $returned) {
            $rows = collect($rows)->keyBy('id');
            $review = $rows[$inReview->id];
            $back = $rows[$returned->id];

            return $review['formTypeLabel'] === 'Activity Proposal'
                && ! str_contains($review['title'], 'Activity Proposal:')
                && $review['waitingOn'] === $this->adviserOne->name
                && $review['waitingOnLine'] === 'Adviser, step 1 of 7'
                && $review['idleTone'] === 'neutral'
                && $back['waitingOn'] === 'The organization'
                && $back['waitingOnLine'] === 'Returned for revision'
                && $back['college'] === 'No college'
                && $back['idleTone'] === 'warning';
        })
    );
});

test('rows are listed longest idle first', function () {
    $short = stuckDoc($this->org, 2);
    $long = stuckDoc($this->org, 30);
    $mid = stuckDoc($this->org, 9);

    stuckPage($this)->assertInertia(fn ($page) => $page->where('documents.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$long->id, $mid->id, $short->id]));
});

// ---- Remind ----------------------------------------------------------------

function remind(mixed $test, Document $doc, ?User $as = null): TestResponse
{
    return $test->actingAs($as ?? $test->sdaoA)->post(route('admin.stuck-documents.remind', $doc));
}

test('a reminder for a document in review goes to the approver it is waiting on', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 6);

    remind($this, $doc)->assertRedirect()->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertSentTo($this->adviserOne, StuckDocumentReminderNotification::class, fn ($n) => $n->toApprover === true && $n->idleDays === 6);
    Notification::assertCount(1);
    expect(DocumentReminder::where('document_id', $doc->id)->where('sent_by', $this->sdaoA->id)->where('recipient_count', 1)->exists())->toBeTrue();
});

test('a reminder for the SDAO step goes to the members who have not approved yet', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 6, formType: FormType::OrganizationRegistration);
    DocumentStepApproval::create([
        'document_id' => $doc->id,
        'workflow_step_id' => $doc->workflowTemplate->steps->firstWhere('position', 1)->id,
        'step_position' => 1,
        'user_id' => $this->sdaoA->id,
    ]);

    remind($this, $doc, $this->sdaoB)->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertSentTo($this->sdaoB, StuckDocumentReminderNotification::class);
    Notification::assertNotSentTo($this->sdaoA, StuckDocumentReminderNotification::class);
    Notification::assertCount(1);
});

test('a reminder for a returned document goes to the organization president and secretary', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 12, DocumentStatus::Returned);
    $officers = app(OrganizationMembershipService::class)->activeOfficersFor($this->org);
    expect($officers)->toHaveCount(2);

    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');

    foreach ($officers as $officer) {
        Notification::assertSentTo($officer, StuckDocumentReminderNotification::class, fn ($n) => $n->toApprover === false);
    }
    Notification::assertNotSentTo($this->adviserOne, StuckDocumentReminderNotification::class);
    Notification::assertCount(2);
});

test('a returned document with no active officers reminds its submitter', function () {
    Notification::fake();
    $founder = User::factory()->create();
    $organization = Organization::factory()->create();
    $doc = stuckDoc($organization, 5, DocumentStatus::Returned, formType: FormType::OrganizationRegistration, submitter: $founder);

    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertSentTo($founder, StuckDocumentReminderNotification::class);
    Notification::assertCount(1);
});

test('the bell row links to the review screen for an approver and the document page for officers', function () {
    Mail::fake();
    $review = stuckDoc($this->org, 6);
    $returned = stuckDoc($this->org, 12, DocumentStatus::Returned);

    remind($this, $review);
    remind($this, $returned);

    $approverRow = $this->adviserOne->notifications()->where('type', StuckDocumentReminderNotification::class)->firstOrFail();
    expect($approverRow->data['kind'])->toBe('stuck_document_reminder')
        ->and($approverRow->data['url'])->toBe(route('review.activity-proposals.show', $review, absolute: false))
        ->and($approverRow->data['document_id'])->toBe($review->id);

    $officerRow = $this->studentAlpha->notifications()->where('type', StuckDocumentReminderNotification::class)->firstOrFail();
    expect($officerRow->data['url'])->toBe(route('activity-proposals.show', $returned, absolute: false))
        ->and($officerRow->data['title'])->toContain('needs your changes');
});

test('only SDAO members can send a reminder', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 6);

    foreach ([$this->studentAlpha, $this->adviserOne] as $user) {
        remind($this, $doc, $user)->assertForbidden();
    }
    auth()->logout();
    $this->post(route('admin.stuck-documents.remind', $doc))->assertRedirect(route('login'));

    Notification::assertNothingSent();
    expect(DocumentReminder::count())->toBe(0);
});

test('a document can be reminded once every 24 hours', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 6);

    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');
    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder not sent');

    $this->travel(23)->hours();
    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder not sent');

    Notification::assertCount(1);
    expect(DocumentReminder::count())->toBe(1);

    $this->travel(61)->minutes();
    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertCount(2);
    expect(DocumentReminder::count())->toBe(2);
});

test('the limit is per document', function () {
    Notification::fake();
    $a = stuckDoc($this->org, 6);
    $b = stuckDoc($this->itGuild, 6);

    remind($this, $a)->assertSessionHas('flash.title', 'Reminder sent');
    remind($this, $b)->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertCount(2);
});

test('the page says when a reminded document can be reminded again', function () {
    Notification::fake();
    $reminded = stuckDoc($this->org, 9);
    $other = stuckDoc($this->itGuild, 3);
    remind($this, $reminded);

    stuckPage($this)->assertInertia(fn ($page) => $page->where('documents.data', function ($rows) use ($reminded, $other) {
        $rows = collect($rows)->keyBy('id');

        // 12:00 UTC on 9/16 is 8:00 PM in Manila; the next one is a day later.
        return $rows[$reminded->id]['remindAvailableLabel'] === '9/17/2026 8:00 PM'
            && $rows[$other->id]['remindAvailableLabel'] === null;
    }));
});

test('a document that is no longer open cannot be reminded', function () {
    Notification::fake();
    $doc = stuckDoc($this->org, 6, DocumentStatus::Approved);

    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder not sent');

    Notification::assertNothingSent();
    expect(DocumentReminder::count())->toBe(0);
});

test('a step with no one assigned cannot be reminded', function () {
    Notification::fake();
    $organization = Organization::factory()->create(); // no adviser
    $doc = stuckDoc($organization, 6);

    remind($this, $doc)->assertSessionHas('flash.title', 'Reminder not sent');

    Notification::assertNothingSent();
    expect(DocumentReminder::count())->toBe(0);
});
