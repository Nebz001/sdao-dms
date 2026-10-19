<?php

use App\Approval\StepDeadline;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Mail\StuckDocumentReminderMail;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use App\Notifications\StuckDocumentReminderNotification;
use Carbon\Carbon;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/*
 * Lead time per approval step, the hourly 24 hour reminder with its overdue
 * alert, and the shared 12 hour cooldown on the manual Remind button. Time is
 * moved with travelTo(); nothing here waits.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->start = Carbon::parse('2026-09-16 12:00:00');
    $this->travelTo($this->start);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/** A proposal that reached its adviser step (position 1) right now. */
function proposalAtAdviser(Organization $org): Document
{
    $template = WorkflowTemplate::where('form_type', FormType::ActivityProposal)->where('variant', ProposalVariant::RegularOnCalendar)->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $org->id,
        'workflow_template_id' => $template->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'title' => 'Reminder Doc',
    ]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);

    return $doc;
}

function atHoursAfterArrival(mixed $test, int $hours, int $minutes = 0): void
{
    $test->travelTo($test->start->copy()->addHours($hours)->addMinutes($minutes));
}

function runReminders(mixed $test): void
{
    $test->artisan('documents:send-step-reminders')->assertSuccessful();
}

function adminRemind(mixed $test, Document $doc, ?User $as = null): TestResponse
{
    return $test->actingAs($as ?? $test->sdaoA)->post(route('admin.stuck-documents.remind', $doc));
}

// ---- Lead time -------------------------------------------------------------

test('every step defaults to a 3 day lead time', function () {
    expect(WorkflowStep::query()->pluck('lead_time_days')->unique()->all())->toBe([3]);
});

test('the due reading counts from the latest transition, in days', function () {
    $doc = proposalAtAdviser($this->org);

    $deadline = fn () => StepDeadline::for($doc->fresh())->toArray();

    expect($deadline())->toMatchArray(['label' => 'Due in 3 days', 'state' => 'on_track', 'leadTimeDays' => 3]);

    atHoursAfterArrival($this, 24);
    expect($deadline())->toMatchArray(['label' => 'Due in 2 days', 'state' => 'on_track']);

    atHoursAfterArrival($this, 49);
    expect($deadline())->toMatchArray(['label' => 'Due in 1 day', 'state' => 'due_soon']);

    atHoursAfterArrival($this, 72);
    expect($deadline())->toMatchArray(['label' => 'Overdue by less than a day', 'state' => 'overdue']);

    atHoursAfterArrival($this, 96);
    expect($deadline())->toMatchArray(['label' => 'Overdue by 1 day', 'state' => 'overdue']);
});

test('a new transition restarts the clock, and updated_at is ignored', function () {
    $doc = proposalAtAdviser($this->org);
    atHoursAfterArrival($this, 50);

    // A stale-looking updated_at must not matter.
    Document::whereKey($doc->id)->update(['updated_at' => now()->subDays(30)]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Advanced,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);

    expect(StepDeadline::for($doc->fresh())->label())->toBe('Due in 3 days');
});

test('a changed lead time moves the due time of a document already waiting', function () {
    $doc = proposalAtAdviser($this->org);
    $doc->workflowTemplate->steps->firstWhere('position', 1)->update(['lead_time_days' => 5]);

    expect(StepDeadline::for($doc->fresh())->toArray())->toMatchArray(['label' => 'Due in 5 days', 'leadTimeLabel' => '5 days']);
});

// ---- Automatic reminders ---------------------------------------------------

test('reminders go out 24 hours apart, never twice for the same mark, and stop once the approver acts', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 23, 59);
    runReminders($this);
    Notification::assertNothingSent();

    atHoursAfterArrival($this, 24);
    runReminders($this);
    runReminders($this); // a double run, or a restart, sends nothing new
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);

    atHoursAfterArrival($this, 30);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);

    atHoursAfterArrival($this, 48);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);

    // The adviser approves: the document moves to the next step.
    atHoursAfterArrival($this, 50);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->adviserOne->id,
        'action' => TransitionAction::Advanced,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
    $doc->update(['current_step_position' => 2]);

    atHoursAfterArrival($this, 100);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);

    expect(DocumentReminder::where('document_id', $doc->id)->where('step_position', 1)->count())->toBe(2);
});

test('a run after a long gap sends one reminder for the current mark, not one per missed day', function () {
    Notification::fake();
    proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 24 * 2 + 5);
    runReminders($this);

    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);
    expect(DocumentReminder::where('slot', 2)->count())->toBe(1);
});

test('documents that are not waiting on an approver get no automatic reminder', function () {
    Notification::fake();
    $returned = proposalAtAdviser($this->org);
    $returned->update(['status' => DocumentStatus::Returned]);
    $approved = proposalAtAdviser($this->org);
    $approved->update(['status' => DocumentStatus::Approved]);

    atHoursAfterArrival($this, 30);
    runReminders($this);

    Notification::assertNothingSent();
    expect(DocumentReminder::count())->toBe(0);
});

test('the reminder switches to an overdue alert at the lead time, and SDAO is told only once', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 48);
    runReminders($this);
    Notification::assertSentTo($this->adviserOne, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_REMINDER);
    Notification::assertNotSentTo($this->sdaoA, StuckDocumentReminderNotification::class);

    atHoursAfterArrival($this, 72);
    runReminders($this);
    Notification::assertSentTo($this->adviserOne, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_OVERDUE);
    foreach ([$this->sdaoA, $this->sdaoB] as $sdao) {
        Notification::assertSentToTimes($sdao, StuckDocumentReminderNotification::class, 1);
        Notification::assertSentTo($sdao, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_SDAO_OVERDUE);
    }

    // Every later day the approver is nagged again; SDAO is not.
    foreach ([96, 120] as $hours) {
        atHoursAfterArrival($this, $hours);
        runReminders($this);
    }
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 4);
    Notification::assertSentToTimes($this->sdaoA, StuckDocumentReminderNotification::class, 1);
    Notification::assertSentToTimes($this->sdaoB, StuckDocumentReminderNotification::class, 1);
    expect(DocumentReminder::where('document_id', $doc->id)->where('kind', DocumentReminder::KIND_SDAO_OVERDUE)->count())->toBe(1);
});

test('the overdue alert follows the lead time set on the step', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);
    $doc->workflowTemplate->steps->firstWhere('position', 1)->update(['lead_time_days' => 1]);

    atHoursAfterArrival($this, 24);
    runReminders($this);

    Notification::assertSentTo($this->adviserOne, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_OVERDUE);
    Notification::assertSentTo($this->sdaoA, StuckDocumentReminderNotification::class, fn ($n) => $n->variant === StuckDocumentReminderMail::VARIANT_SDAO_OVERDUE);
});

test('reminders reach both the email and the bell, with different wording once overdue', function () {
    Mail::fake();
    proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 24);
    runReminders($this);
    atHoursAfterArrival($this, 72);
    runReminders($this);

    $rows = $this->adviserOne->notifications()->where('type', StuckDocumentReminderNotification::class)->get()->keyBy(fn ($row) => $row->data['kind']);

    expect($rows)->toHaveCount(2)
        ->and($rows['stuck_document_reminder']->data['title'])->toStartWith('Reminder:')
        ->and($rows['step_overdue']->data['title'])->toStartWith('Overdue:');
});

test('the command is scheduled hourly', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'documents:send-step-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});

// ---- Manual Remind and its cooldown ----------------------------------------

test('after one admin reminds a step, no admin can for 12 hours', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);
    atHoursAfterArrival($this, 5);

    adminRemind($this, $doc, $this->sdaoA)->assertSessionHas('flash.title', 'Reminder sent');

    atHoursAfterArrival($this, 6);
    adminRemind($this, $doc, $this->sdaoB)->assertSessionHas('flash.title', 'Reminder not sent');

    atHoursAfterArrival($this, 16, 59);
    adminRemind($this, $doc, $this->sdaoB)->assertSessionHas('flash.title', 'Reminder not sent');

    atHoursAfterArrival($this, 17, 1);
    adminRemind($this, $doc, $this->sdaoB)->assertSessionHas('flash.title', 'Reminder sent');

    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);
});

test('an automatic reminder locks the button too', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 24);
    runReminders($this);

    atHoursAfterArrival($this, 30);
    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder not sent');
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);

    atHoursAfterArrival($this, 36, 1);
    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);
});

test('a direct request during the cooldown is refused on the server with a friendly message', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);
    atHoursAfterArrival($this, 2);
    adminRemind($this, $doc);

    $response = adminRemind($this, $doc, $this->sdaoB);

    $response->assertRedirect()->assertSessionHas('flash.title', 'Reminder not sent');
    expect(session('flash.message'))->toContain('already sent')->toContain('You can send another after');
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1);
    expect(DocumentReminder::where('kind', DocumentReminder::KIND_MANUAL)->count())->toBe(1);
});

test('a manual reminder does not move the automatic 24 hour schedule', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);

    atHoursAfterArrival($this, 10);
    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');

    // The automatic mark is still 24 hours after arrival, not 24 after the manual send.
    atHoursAfterArrival($this, 23, 59);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 1); // the manual one only

    atHoursAfterArrival($this, 24);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);

    atHoursAfterArrival($this, 48);
    runReminders($this);
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 3);

    $manual = DocumentReminder::where('kind', DocumentReminder::KIND_MANUAL)->firstOrFail();
    expect($manual->slot)->toBeNull();
    expect(DocumentReminder::whereNotNull('slot')->where('kind', '!=', DocumentReminder::KIND_SDAO_OVERDUE)->pluck('slot')->sort()->values()->all())->toBe([1, 2]);
});

test('the SDAO overdue alert does not lock the button', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);
    $doc->workflowTemplate->steps->firstWhere('position', 1)->update(['lead_time_days' => 1]);

    atHoursAfterArrival($this, 24);
    runReminders($this);
    atHoursAfterArrival($this, 37);

    // The approver's own reminder at 24h is 13 hours old, so nothing locks the button.
    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');
});

test('a reminder for an earlier visit to a step does not lock the next one', function () {
    Notification::fake();
    $doc = proposalAtAdviser($this->org);
    atHoursAfterArrival($this, 1);
    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');

    // The document is returned and resubmitted: a new arrival at the same step.
    atHoursAfterArrival($this, 2);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'action' => TransitionAction::Resubmitted,
        'from_status' => DocumentStatus::Returned,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);

    adminRemind($this, $doc)->assertSessionHas('flash.title', 'Reminder sent');
    Notification::assertSentToTimes($this->adviserOne, StuckDocumentReminderNotification::class, 2);
});

test('the stuck documents page reports the unlock time, the last reminder and the due reading', function () {
    Notification::fake();
    proposalAtAdviser($this->org);
    atHoursAfterArrival($this, 24);
    runReminders($this);

    $this->actingAs($this->sdaoA)->get(route('admin.stuck-documents.index'))->assertInertia(fn ($page) => $page
        ->where('documents.data.0.lastReminderKind', 'automatic')
        ->where('documents.data.0.deadline.label', 'Due in 2 days')
        ->where('documents.data.0.remindAvailableAt', fn ($value) => Carbon::parse($value)->equalTo(now()->addHours(12)))
    );
});

// ---- The SDAO screen -------------------------------------------------------

test('SDAO can see and change the lead time of a step', function () {
    $step = WorkflowStep::whereHas('template', fn ($q) => $q->where('form_type', FormType::OrganizationRegistration))->firstOrFail();

    $this->actingAs($this->sdaoA)->get(route('admin.settings.lead-times.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/settings/lead-times')->has('templates'));

    $this->actingAs($this->sdaoA)->put(route('admin.settings.lead-times.update'), ['lead_times' => [$step->id => 7]])
        ->assertRedirect(route('admin.settings.lead-times.edit'))
        ->assertSessionHas('flash.title', 'Lead times saved');

    expect($step->fresh()->lead_time_days)->toBe(7);
});

test('a lead time must be a whole number of days from 1 to 60', function (mixed $value) {
    $step = WorkflowStep::firstOrFail();

    $this->actingAs($this->sdaoA)->put(route('admin.settings.lead-times.update'), ['lead_times' => [$step->id => $value]])
        ->assertSessionHasErrors("lead_times.{$step->id}");

    expect($step->fresh()->lead_time_days)->toBe(3);
})->with([0, 61, 'soon', 2.5, '']);

test('only SDAO can open or change lead times', function () {
    $step = WorkflowStep::firstOrFail();

    foreach ([$this->studentAlpha, $this->adviserOne] as $user) {
        $this->actingAs($user)->get(route('admin.settings.lead-times.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.lead-times.update'), ['lead_times' => [$step->id => 9]])->assertForbidden();
    }

    expect($step->fresh()->lead_time_days)->toBe(3);
});
