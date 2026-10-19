<?php

use App\Approval\StepDeadline;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Mail\StuckDocumentReminderMail;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Notifications\StuckDocumentReminderNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/*
 * The lead-time clock and the reminder schedule belong to a VISIT to a step
 * (Submitted, Advanced or Resubmitted), not to the latest transition of any
 * kind: a partial SDAO approval must not move them. And the hourly command
 * never bursts: one reminder per document and visit for the current 24 hour
 * mark only, however long the scheduler was off.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->visitStart = Carbon::parse('2026-09-16 12:00:00');
    $this->travelTo($this->visitStart);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

/** A registration that reached its SDAO step (position 1) at $arrivedAt. */
function registrationAtSdao(Organization $org, ?CarbonInterface $arrivedAt = null): Document
{
    $template = WorkflowTemplate::where('form_type', FormType::OrganizationRegistration)->whereNull('variant')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'title' => 'Visit Clock Doc',
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => $arrivedAt ?? now(),
    ]);

    return $doc;
}

/** A proposal that reached its adviser step (position 1) at $arrivedAt. */
function proposalAtAdviserSince(Organization $org, CarbonInterface $arrivedAt): Document
{
    $template = WorkflowTemplate::where('form_type', FormType::ActivityProposal)->where('variant', ProposalVariant::RegularOnCalendar)->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'title' => 'Waited Ten Days',
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => $arrivedAt,
    ]);

    return $doc;
}

/** The first of SDAO's two approvals: a transition and an approval row, but the document stays at the step. */
function partialSdaoApproval(Document $doc, User $approver): void
{
    DocumentStepApproval::create([
        'document_id' => $doc->id,
        'workflow_step_id' => $doc->workflowTemplate->steps->firstWhere('position', 1)->id,
        'step_position' => 1,
        'user_id' => $approver->id,
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $approver->id,
        'action' => TransitionAction::Approved,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
}

function travelHoursFromVisitStart(mixed $test, int $hours): void
{
    $test->travelTo($test->visitStart->copy()->addHours($hours));
}

// ---- Partial approvals do not restart the clock ------------------------------

test('a partial SDAO approval does not move the due time', function () {
    $doc = registrationAtSdao($this->org);
    $arrival = $doc->transitions()->firstOrFail();
    $dueBefore = StepDeadline::for($doc->fresh())->dueAt->toIso8601String();

    travelHoursFromVisitStart($this, 30);
    partialSdaoApproval($doc, $this->sdaoA);

    $deadline = StepDeadline::for($doc->fresh());

    expect($deadline->dueAt->toIso8601String())->toBe($dueBefore)
        ->and($deadline->arrivalTransitionId)->toBe($arrival->id)
        ->and($deadline->toArray())->toMatchArray(['label' => 'Due in 2 days', 'state' => 'on_track']);

    travelHoursFromVisitStart($this, 72);
    expect(StepDeadline::for($doc->fresh())->state())->toBe('overdue');
});

test('a partial approval does not move the reminder schedule, and only the approver who has not acted is reminded', function () {
    Notification::fake();
    $doc = registrationAtSdao($this->org);

    travelHoursFromVisitStart($this, 24);
    $this->artisan('documents:send-step-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->sdaoA, StuckDocumentReminderNotification::class, 1);
    Notification::assertSentToTimes($this->sdaoB, StuckDocumentReminderNotification::class, 1);

    travelHoursFromVisitStart($this, 30);
    partialSdaoApproval($doc, $this->sdaoA);

    // The next mark is still 48 hours after arrival, not 24 after the approval.
    travelHoursFromVisitStart($this, 47);
    $this->artisan('documents:send-step-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->sdaoB, StuckDocumentReminderNotification::class, 1);

    travelHoursFromVisitStart($this, 48);
    $this->artisan('documents:send-step-reminders')->assertSuccessful();
    Notification::assertSentToTimes($this->sdaoB, StuckDocumentReminderNotification::class, 2);
    Notification::assertSentToTimes($this->sdaoA, StuckDocumentReminderNotification::class, 1); // only the one before approving

    expect(DocumentReminder::where('document_id', $doc->id)->whereNotNull('slot')->pluck('slot')->sort()->values()->all())->toBe([1, 2]);
});

test('the overdue alert after a partial approval goes only to the approver who has not acted', function () {
    Notification::fake();
    $doc = registrationAtSdao($this->org);
    travelHoursFromVisitStart($this, 30);
    partialSdaoApproval($doc, $this->sdaoA);

    travelHoursFromVisitStart($this, 72);
    $this->artisan('documents:send-step-reminders')->assertSuccessful();

    Notification::assertSentTo($this->sdaoB, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_OVERDUE);
    Notification::assertNotSentTo($this->sdaoA, StuckDocumentReminderNotification::class);
    Notification::assertCount(1);
});

test('a return and resubmit starts a new visit with a fresh clock', function () {
    $doc = registrationAtSdao($this->org);
    travelHoursFromVisitStart($this, 30);
    partialSdaoApproval($doc, $this->sdaoA);

    $doc->update(['status' => DocumentStatus::Returned]);
    travelHoursFromVisitStart($this, 50);
    $resubmitted = DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Resubmitted,
        'from_status' => DocumentStatus::Returned,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
    $doc->update(['status' => DocumentStatus::InReview]);

    $deadline = StepDeadline::for($doc->fresh());

    expect($deadline->arrivalTransitionId)->toBe($resubmitted->id)
        ->and($deadline->label())->toBe('Due in 3 days');
});

// ---- No burst on the first deploy --------------------------------------------

test('a document that waited 10 days gets exactly one overdue reminder and one SDAO alert, and a second run in the hour sends nothing', function () {
    Notification::fake();
    proposalAtAdviserSince($this->org, now()->subDays(10));
    expect(DocumentReminder::count())->toBe(0);

    $this->artisan('documents:send-step-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);
    Notification::assertSentTo($this->adviserOne, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_OVERDUE && $n->overdueDays === 7);
    foreach ([$this->sdaoA, $this->sdaoB] as $sdao) {
        Notification::assertSentToTimes($sdao, StuckDocumentReminderNotification::class, 1);
        Notification::assertSentTo($sdao, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_SDAO_OVERDUE);
    }
    Notification::assertCount(3);
    expect(DocumentReminder::orderBy('slot')->pluck('slot')->all())->toBe([0, 10]);

    // Same hour, and a little later in it: nothing new.
    $this->artisan('documents:send-step-reminders')->assertSuccessful();
    $this->travel(40)->minutes();
    $this->artisan('documents:send-step-reminders')->assertSuccessful();

    Notification::assertCount(3);
    expect(DocumentReminder::count())->toBe(2);
});

test('after a long outage the next run sends one reminder for the current mark, never the missed ones', function () {
    Notification::fake();
    proposalAtAdviserSince($this->org, now()->subDays(10));

    $this->artisan('documents:send-step-reminders')->assertSuccessful();
    Notification::assertCount(3);

    // The scheduler is off for four days, then comes back.
    $this->travel(4)->days();
    $this->artisan('documents:send-step-reminders')->assertSuccessful();

    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);
    Notification::assertSentToTimes($this->sdaoA, StuckDocumentReminderNotification::class, 1);
    expect(DocumentReminder::whereNotNull('slot')->where('slot', '>', 0)->pluck('slot')->sort()->values()->all())->toBe([10, 14]);
});
