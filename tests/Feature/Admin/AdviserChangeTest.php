<?php

use App\Approval\ApprovalEngine;
use App\Approval\ApproverQueue;
use App\Dashboard\AdminAttentionData;
use App\Enums\AdviserTermOutcome;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Enums\TransitionAction;
use App\Mail\ApproverHandOffMail;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\AdviserTerm;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\AdviserAssignedNotification;
use App\Notifications\AdviserRoleEndedNotification;
use App\Notifications\ApproverHandOffNotification;
use App\Notifications\OrganizationAdviserChangedNotification;
use App\Organizations\Admin\AssignOrganizationAdviser;
use App\Organizations\OrganizationDetailData;
use App\Organizations\OrganizationMembershipService;
use App\Printing\ActivityProposalForm;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->action = app(AssignOrganizationAdviser::class);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->otherOrg = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->oldAdviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

/** An active adviser sitting in the unassigned pool. */
function poolAdviser(string $name = 'Pool Adviser'): User
{
    $user = User::factory()->create(['name' => $name, 'account_status' => 'verified']);
    RoleAssignment::create(['user_id' => $user->id, 'role' => Role::Adviser]);

    return $user;
}

function boundAdviserIds(Organization $org): array
{
    return RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $org->id)->pluck('user_id')->all();
}

function swapAdviser(?User $incoming = null, AdviserTermOutcome $outcome = AdviserTermOutcome::ReturnedToPool, ?Organization $org = null): User
{
    $incoming ??= poolAdviser();

    app(AssignOrganizationAdviser::class)->execute(
        test()->sdaoA, $org ?? test()->org, $incoming, $outcome,
    );

    return $incoming;
}

/** A proposal in review at the adviser step (position 1) for the org. */
function proposalAtAdviserStep(Organization $org, User $submitter, string $title = 'Waiting Proposal'): Document
{
    $doc = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar->value,
        'title' => $title,
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);
    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar, 'variant' => null, 'title' => 'Backing Calendar',
        'status' => DocumentStatus::Approved, 'current_step_position' => null,
        'organization_id' => $org->id, 'workflow_template_id' => null, 'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create(['document_id' => $calendarDoc->id, 'academic_year' => '2025-2026', 'term' => 'first_term']);
    $activity = CalendarActivity::create([
        'activity_calendar_id' => $calendar->id, 'name' => 'Hack Night', 'venue' => 'Room 101',
        'activity_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '11:00',
    ]);
    ActivityProposal::create([
        'document_id' => $doc->id, 'calendar_mode' => 'on_calendar', 'calendar_activity_id' => $activity->id,
        'title' => $title, 'activity_nature' => 'co_curricular', 'activity_type' => 'competition',
        'partner_organizations' => [], 'target_sdg' => ['life_on_land'], 'objectives' => 'Goal',
        'activity_description' => 'Description', 'criteria_mechanics' => 'Criteria', 'program_flow' => 'Flow',
        'expenses' => 'Venue', 'proposed_budget' => 5000, 'budget_source' => 'rso_fund', 'form_step' => 2,
        'president_name' => null,
    ]);

    app(ApprovalEngine::class)->submit($doc, $submitter);

    return $doc->refresh();
}

// ── History ──────────────────────────────────────────────────────────────

test('a swap closes the old adviser term, opens a new one, and records who and when', function () {
    $this->travelTo(now()->startOfSecond());
    AdviserTerm::create(['user_id' => $this->oldAdviser->id, 'organization_id' => $this->org->id, 'started_at' => now()->subYear()]);

    $incoming = swapAdviser();

    $closed = AdviserTerm::where('user_id', $this->oldAdviser->id)->firstOrFail();
    expect($closed->ended_at->equalTo(now()))->toBeTrue()
        ->and($closed->ended_by)->toBe($this->sdaoA->id)
        ->and($closed->end_outcome)->toBe(AdviserTermOutcome::ReturnedToPool);

    $open = AdviserTerm::where('organization_id', $this->org->id)->open()->get();
    expect($open)->toHaveCount(1)
        ->and($open->first()->user_id)->toBe($incoming->id)
        ->and($open->first()->started_by)->toBe($this->sdaoA->id)
        ->and($open->first()->started_at->equalTo(now()))->toBeTrue();
});

test('an outgoing adviser with no term on record still lands in the history, already closed', function () {
    expect(AdviserTerm::where('user_id', $this->oldAdviser->id)->exists())->toBeFalse();

    swapAdviser();

    $closed = AdviserTerm::where('user_id', $this->oldAdviser->id)->firstOrFail();
    expect($closed->ended_at)->not->toBeNull()
        ->and($closed->organization_id)->toBe($this->org->id)
        ->and($closed->ended_by)->toBe($this->sdaoA->id);
});

test('two swaps in a row leave one bound adviser and a complete history', function () {
    $first = swapAdviser(poolAdviser('First'));
    $second = swapAdviser(poolAdviser('Second'));

    expect(boundAdviserIds($this->org))->toBe([$second->id]);
    expect(AdviserTerm::where('organization_id', $this->org->id)->count())->toBe(3);
    expect(AdviserTerm::where('organization_id', $this->org->id)->open()->count())->toBe(1);
    expect(AdviserTerm::where('user_id', $first->id)->firstOrFail()->ended_at)->not->toBeNull();
});

test('the backfill opens one term per bound adviser, dated from the role assignment', function () {
    $assignment = RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->firstOrFail();
    DB::table('role_assignments')->where('id', $assignment->id)->update(['created_at' => '2025-03-04 05:06:07']);

    Schema::dropIfExists('adviser_terms');
    (require database_path('migrations/2026_10_06_174014_create_adviser_terms_table.php'))->up();

    $terms = DB::table('adviser_terms')->where('organization_id', $this->org->id)->get();
    expect($terms)->toHaveCount(1)
        ->and($terms->first()->user_id)->toBe($this->oldAdviser->id)
        ->and($terms->first()->started_at)->toBe('2025-03-04 05:06:07')
        ->and($terms->first()->ended_at)->toBeNull()
        ->and($terms->first()->started_by)->toBeNull();

    // Unbound pool advisers get nothing.
    $pool = poolAdviser();
    expect(DB::table('adviser_terms')->where('user_id', $pool->id)->exists())->toBeFalse();
});

// ── Swap behavior ────────────────────────────────────────────────────────

test('the outgoing adviser returns to the pool, or is deactivated, as SDAO chooses', function () {
    swapAdviser(outcome: AdviserTermOutcome::ReturnedToPool);

    $row = RoleAssignment::where('user_id', $this->oldAdviser->id)->where('role', Role::Adviser->value)->firstOrFail();
    expect($row->organization_id)->toBeNull();
    expect($this->oldAdviser->fresh()->deactivated_at)->toBeNull();

    $second = poolAdviser();
    $current = User::find(boundAdviserIds($this->org)[0]);
    swapAdviser($second, AdviserTermOutcome::Deactivated);

    expect($current->fresh()->deactivated_at)->not->toBeNull();
    expect(RoleAssignment::where('user_id', $current->id)->where('role', Role::Adviser->value)->value('organization_id'))->toBeNull();
    expect(AdviserTerm::where('user_id', $current->id)->firstOrFail()->end_outcome)->toBe(AdviserTermOutcome::Deactivated);
});

test('only an active, unassigned pool adviser can be assigned', function () {
    $deactivated = poolAdviser();
    $deactivated->forceFill(['deactivated_at' => now()])->save();
    $boundElsewhere = User::where('email', 'adviser-two@nu-lipa.edu.ph')->first() ?? User::find(boundAdviserIds($this->otherOrg)[0] ?? 0);
    $student = $this->president;

    foreach ([$deactivated, $boundElsewhere, $this->oldAdviser, $student] as $candidate) {
        expect(fn () => swapAdviser($candidate))->toThrow(ValidationException::class);
    }

    expect(boundAdviserIds($this->org))->toBe([$this->oldAdviser->id]);
    expect(AdviserTerm::count())->toBe(0);
});

test('only an SDAO member can change an adviser', function () {
    expect(fn () => $this->action->swap($this->oldAdviser, $this->org, poolAdviser(), AdviserTermOutcome::ReturnedToPool))
        ->toThrow(AuthorizationException::class);

    expect(boundAdviserIds($this->org))->toBe([$this->oldAdviser->id]);
});

test('a swap confirmed against a stale page is refused before anything changes', function () {
    $someoneElse = poolAdviser('Someone Else');
    swapAdviser($someoneElse);

    // The page SDAO is looking at still shows the old adviser as current.
    $candidate = poolAdviser('Candidate');

    $attempt = fn () => $this->action->swap($this->sdaoA, $this->org, $candidate, AdviserTermOutcome::Deactivated, verifyOutgoing: true, expectedOutgoingUserId: $this->oldAdviser->id);

    expect($attempt)->toThrow(ValidationException::class, 'changed while you were on this page');
    expect(boundAdviserIds($this->org))->toBe([$someoneElse->id]);
    expect($someoneElse->fresh()->deactivated_at)->toBeNull();
});

test('two swaps colliding on one organization: the loser gets a plain message and nothing is half applied', function () {
    $winner = poolAdviser('Winner');
    $loser = poolAdviser('Loser');

    // The rival lands in the window after our checks, before our bind.
    RoleAssignment::updating(function (RoleAssignment $assignment) use ($winner, $loser) {
        static $fired = false;

        if ($fired || $assignment->user_id !== $loser->id) {
            return;
        }

        $fired = true;
        DB::table('role_assignments')->where('user_id', $winner->id)->where('role', 'adviser')->update(['organization_id' => $this->org->id]);
    });

    expect(fn () => swapAdviser($loser))
        ->toThrow(ValidationException::class, 'Someone else just changed this organization');

    // The rival's bind survives; the loser did not become adviser and the
    // outgoing adviser's unbind rolled back (the rival's row is the only one).
    expect(boundAdviserIds($this->org))->not->toContain($loser->id);
    expect(RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->count())->toBeLessThanOrEqual(1);
    expect(AdviserTerm::where('user_id', $loser->id)->exists())->toBeFalse();
});

test('a swap on the other organization is unaffected', function () {
    $before = boundAdviserIds($this->otherOrg);

    swapAdviser();

    expect(boundAdviserIds($this->otherOrg))->toBe($before);
});

// ── Database rules ───────────────────────────────────────────────────────

test('the database refuses a second bound adviser on one organization', function () {
    $extra = poolAdviser();

    expect(fn () => RoleAssignment::where('user_id', $extra->id)->update(['organization_id' => $this->org->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the database refuses one adviser bound to two organizations', function () {
    $free = Organization::factory()->create();

    expect(fn () => RoleAssignment::create(['user_id' => $this->oldAdviser->id, 'role' => Role::Adviser, 'organization_id' => $free->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('many advisers may sit unbound in the pool', function () {
    poolAdviser('One');
    poolAdviser('Two');
    poolAdviser('Three');

    expect(RoleAssignment::where('role', Role::Adviser->value)->whereNull('organization_id')->count())->toBeGreaterThanOrEqual(3);
});

test('the database refuses two open adviser terms for one organization', function () {
    AdviserTerm::create(['user_id' => $this->oldAdviser->id, 'organization_id' => $this->org->id, 'started_at' => now()]);

    expect(fn () => AdviserTerm::create(['user_id' => poolAdviser()->id, 'organization_id' => $this->org->id, 'started_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the index migration names the offending rows and changes nothing when the data already conflicts', function () {
    foreach ([
        'role_assignments_one_bound_adviser_per_organization',
        'role_assignments_one_bound_organization_per_adviser',
        'adviser_terms_one_open_per_organization',
        'adviser_terms_one_open_per_user',
    ] as $index) {
        DB::statement("drop index {$index}");
    }

    $extra = poolAdviser('Extra');
    DB::table('role_assignments')->where('user_id', $extra->id)->update(['organization_id' => $this->org->id]);

    $migration = require database_path('migrations/2026_10_06_174015_add_one_bound_adviser_indexes_to_role_assignments.php');

    try {
        $migration->up();
        $this->fail('The migration should have refused to run.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('Computing Society')
            ->toContain("user {$this->oldAdviser->id}")
            ->toContain("user {$extra->id}")
            ->toContain('Nothing was changed');
    }

    // Still no index: a second row is accepted, proving nothing was created.
    expect(RoleAssignment::where('role', Role::Adviser->value)->where('organization_id', $this->org->id)->count())->toBe(2);
});

// ── In-flight documents ──────────────────────────────────────────────────

test('a document waiting at the adviser step moves to the new adviser at once, and away from the old one', function () {
    $doc = proposalAtAdviserStep($this->org, $this->president);
    expect(Gate::forUser($this->oldAdviser)->allows('review', $doc))->toBeTrue();

    $incoming = swapAdviser();

    $doc->refresh();
    expect(Gate::forUser($incoming)->allows('review', $doc))->toBeTrue()
        ->and(Gate::forUser($this->oldAdviser)->allows('review', $doc))->toBeFalse()
        ->and(Gate::forUser($this->oldAdviser)->allows('view', $doc))->toBeFalse();

    expect(app(ApproverQueue::class)->pendingFor($incoming)->pluck('id')->all())->toContain($doc->id);
    expect(app(ApproverQueue::class)->pendingFor($this->oldAdviser)->pluck('id')->all())->not->toContain($doc->id);
});

test('the old adviser keeps read access to a document they already acted on', function () {
    $doc = proposalAtAdviserStep($this->org, $this->president);
    $this->engine->approve($doc, $this->oldAdviser);

    swapAdviser();

    expect(Gate::forUser($this->oldAdviser)->allows('view', $doc->refresh()))->toBeTrue();
});

test('the officer duties move to the new adviser at once', function () {
    $incoming = swapAdviser();

    expect(Gate::forUser($incoming)->allows('manageOfficers', $this->org))->toBeTrue()
        ->and(Gate::forUser($this->oldAdviser)->allows('manageOfficers', $this->org))->toBeFalse()
        ->and(Gate::forUser($incoming)->allows('manageJoinRequests', $this->org))->toBeTrue()
        ->and(Gate::forUser($this->oldAdviser)->allows('manageJoinRequests', $this->org))->toBeFalse();
});

// ── Printed form ─────────────────────────────────────────────────────────

test('an approved proposal still prints the adviser who signed, not the one who holds the seat now', function () {
    $doc = proposalAtAdviserStep($this->org, $this->president);
    $this->engine->approve($doc, $this->oldAdviser);
    $doc->refresh();

    $incoming = swapAdviser(poolAdviser('Brand New Adviser'));

    $form = app(ActivityProposalForm::class);
    $doc->load($form->eagerLoads());
    $signature = $form->data($doc)['narrative_signatures']['adviser'];

    expect($signature->names)->toBe([$this->oldAdviser->name])
        ->and($signature->date)->not->toBeNull();

    // And before anyone signs, the preview names whoever holds the seat now.
    $unsigned = proposalAtAdviserStep($this->org, $this->president, 'Unsigned Proposal');
    $unsigned->load($form->eagerLoads());
    expect($form->data($unsigned)['narrative_signatures']['adviser']->names)->toBe([$incoming->name]);
});

// ── Notices ──────────────────────────────────────────────────────────────

test('the new adviser gets ONE notice listing waiting documents and pending join requests', function () {
    $first = proposalAtAdviserStep($this->org, $this->president, 'First Waiting');
    proposalAtAdviserStep($this->org, $this->president, 'Second Waiting');
    $elsewhere = proposalAtAdviserStep($this->otherOrg, User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail(), 'Other Org Proposal');
    $this->engine->approve($first, $this->oldAdviser); // moved past the adviser step: not waiting on the adviser
    OrganizationJoinRequest::create(['user_id' => User::factory()->create()->id, 'organization_id' => $this->org->id, 'status' => 'pending']);

    Notification::fake();
    $incoming = swapAdviser();

    Notification::assertSentToTimes($incoming, AdviserAssignedNotification::class, 1);
    Notification::assertSentTo($incoming, AdviserAssignedNotification::class, function (AdviserAssignedNotification $n) use ($incoming) {
        $payload = $n->toArray($incoming);
        $mail = $n->toMail($incoming)->render();

        expect($n->waitingDocumentCount)->toBe(1)
            ->and($n->pendingJoinRequestCount)->toBe(1)
            ->and($n->via($incoming))->toBe(['mail', 'database'])
            ->and($payload['url'])->toBe(route('review.activity-proposals.index', absolute: false))
            ->and($mail)->toContain('Second Waiting')->not->toContain('Other Org Proposal')->not->toContain('First Waiting');

        return true;
    });
    // The hand-off notice is not sent per document.
    Notification::assertNotSentTo($incoming, ApproverHandOffNotification::class);
    expect($elsewhere->current_step_position)->toBe(1);
});

test('a new adviser with nothing waiting is still told, and pointed at the join request queue', function () {
    Notification::fake();
    $incoming = swapAdviser();

    Notification::assertSentTo($incoming, AdviserAssignedNotification::class, function (AdviserAssignedNotification $n) use ($incoming) {
        expect($n->waitingDocumentCount)->toBe(0)
            ->and($n->toArray($incoming)['url'])->toBe(route('review.join-requests.index', absolute: false));

        return true;
    });
});

test('the old adviser is told their role ended and is never told who replaced them', function () {
    Notification::fake();
    $incoming = swapAdviser(poolAdviser('Zebediah Replacement'));

    Notification::assertSentTo($this->oldAdviser, AdviserRoleEndedNotification::class, function (AdviserRoleEndedNotification $n) {
        $payload = json_encode($n->toArray($this->oldAdviser));
        $mail = $n->toMail($this->oldAdviser)->render();

        expect($n->via($this->oldAdviser))->toBe(['mail', 'database'])
            ->and($payload)->toContain('Computing Society')->not->toContain('Zebediah')
            ->and($mail)->toContain('Computing Society')->not->toContain('Zebediah');

        return true;
    });
    Notification::assertNotSentTo($this->oldAdviser, AdviserAssignedNotification::class);
    expect($incoming->name)->toBe('Zebediah Replacement');
});

test('a deactivated old adviser gets the notice by mail only', function () {
    Notification::fake();
    swapAdviser(outcome: AdviserTermOutcome::Deactivated);

    Notification::assertSentTo($this->oldAdviser, AdviserRoleEndedNotification::class, fn ($n) => $n->via($this->oldAdviser) === ['mail']);
});

test('the organization\'s active officers are told who the new adviser is', function () {
    Notification::fake();
    $incoming = swapAdviser(poolAdviser('Zebediah Replacement'));

    $officers = app(OrganizationMembershipService::class)->activeOfficersFor($this->org);
    expect($officers)->not->toBeEmpty();

    foreach ($officers as $officer) {
        Notification::assertSentTo($officer, OrganizationAdviserChangedNotification::class, function ($n) use ($officer) {
            expect(json_encode($n->toArray($officer)))->toContain('Zebediah Replacement');

            return true;
        });
    }

    Notification::assertNotSentTo($incoming, OrganizationAdviserChangedNotification::class);
});

test('a notice that fails to send never undoes the swap', function () {
    // Notifiable::notify() goes through the dispatcher; make every send fail.
    $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new RuntimeException('mail provider down')));
    Log::spy();

    $incoming = poolAdviser();
    $this->action->execute($this->sdaoA, $this->org, $incoming, AdviserTermOutcome::ReturnedToPool);

    expect(boundAdviserIds($this->org))->toBe([$incoming->id]);
    Log::shouldHaveReceived('error')->atLeast()->once();
});

// ── Hand-off wording ─────────────────────────────────────────────────────

test('a resubmission that lands on a new adviser does not claim they returned it', function () {
    $doc = proposalAtAdviserStep($this->org, $this->president);
    $this->engine->returnForRevision($doc, $this->oldAdviser, 'Fix the budget.');
    $doc->refresh();

    $incoming = swapAdviser();

    $newMail = new ApproverHandOffMail($incoming, $doc, 1, TransitionAction::Resubmitted);
    $newMail->assertHasSubject("Action needed: {$doc->title}");
    $newMail->assertSeeInHtml('was revised after being returned', false);
    $newMail->assertDontSeeInHtml('You previously returned this', false);

    // The adviser who really did return it still gets the original wording.
    $sameMail = new ApproverHandOffMail($this->oldAdviser, $doc, 1, TransitionAction::Resubmitted);
    $sameMail->assertHasSubject("Resubmitted for your review: {$doc->title}");
    $sameMail->assertSeeInHtml('You previously returned this', false);
});

// ── Cleanup ──────────────────────────────────────────────────────────────

test('an organization whose only adviser is deactivated counts as having no adviser', function () {
    $approved = Document::create([
        'form_type' => FormType::OrganizationRegistration, 'variant' => null, 'title' => 'Reg',
        'status' => DocumentStatus::Approved, 'current_step_position' => null,
        'organization_id' => $this->org->id, 'workflow_template_id' => null, 'submitted_by' => null,
    ]);
    $attention = app(AdminAttentionData::class);

    expect($attention->approvedOrganizationsWithoutAdviser()->pluck('id')->all())->not->toContain($this->org->id);

    $this->oldAdviser->forceFill(['deactivated_at' => now()])->save();

    expect($attention->approvedOrganizationsWithoutAdviser()->pluck('id')->all())->toContain($this->org->id);
    expect($approved->exists)->toBeTrue();
});

// ── Screens ──────────────────────────────────────────────────────────────

test('SDAO assigns a pool adviser from the organization page and sees a confirmation', function () {
    $incoming = poolAdviser('Pool Pick');

    $this->actingAs($this->sdaoA)
        ->post(route('admin.organizations.adviser.store', $this->org), [
            'adviser_id' => $incoming->id,
            'outgoing_adviser' => 'deactivated',
            'current_adviser_id' => $this->oldAdviser->id,
        ])
        ->assertRedirect(route('admin.organizations.show', $this->org))
        ->assertSessionHas('flash.title', 'Adviser assigned')
        ->assertSessionHas('flash.message', fn ($m) => str_contains($m, 'Pool Pick') && str_contains($m, 'was deactivated'));

    expect(boundAdviserIds($this->org))->toBe([$incoming->id]);
    expect($this->oldAdviser->fresh()->deactivated_at)->not->toBeNull();
});

test('the assign endpoint refuses a stale page and a non-pool adviser with a field error', function () {
    $incoming = poolAdviser();
    swapAdviser(poolAdviser('Newer'));

    $this->actingAs($this->sdaoA)
        ->from(route('admin.organizations.show', $this->org))
        ->post(route('admin.organizations.adviser.store', $this->org), [
            'adviser_id' => $incoming->id,
            'outgoing_adviser' => 'returned_to_pool',
            'current_adviser_id' => $this->oldAdviser->id,
        ])
        ->assertSessionHasErrors('adviser');

    $this->actingAs($this->sdaoA)
        ->post(route('admin.organizations.adviser.store', $this->org), [
            'adviser_id' => $this->president->id,
            'outgoing_adviser' => 'returned_to_pool',
            'current_adviser_id' => null,
        ])
        ->assertSessionHasErrors('adviser_id');
});

test('only SDAO can reach the assign endpoint', function () {
    $this->actingAs($this->oldAdviser)
        ->post(route('admin.organizations.adviser.store', $this->org), [
            'adviser_id' => poolAdviser()->id,
            'outgoing_adviser' => 'returned_to_pool',
            'current_adviser_id' => $this->oldAdviser->id,
        ])
        ->assertForbidden();

    expect(boundAdviserIds($this->org))->toBe([$this->oldAdviser->id]);
});

test('the create-account path uses the same swap, honors the outgoing choice, and rolls the account back on a stale page', function () {
    $this->actingAs($this->sdaoA)
        ->post(route('admin.approvers.store'), [
            'first_name' => 'Fresh', 'last_name' => 'Adviser', 'email' => 'fresh-adviser@nu-lipa.edu.ph',
            'role' => 'adviser', 'organization_id' => $this->org->id,
            'outgoing_adviser' => 'deactivated', 'current_adviser_id' => $this->oldAdviser->id,
        ])
        ->assertRedirect(route('admin.approvers.index'));

    $fresh = User::where('email', 'fresh-adviser@nu-lipa.edu.ph')->firstOrFail();
    expect(boundAdviserIds($this->org))->toBe([$fresh->id]);
    expect($this->oldAdviser->fresh()->deactivated_at)->not->toBeNull();
    expect(AdviserTerm::where('user_id', $fresh->id)->open()->firstOrFail()->started_by)->toBe($this->sdaoA->id);

    // A second attempt still naming the old adviser as current is stale.
    $this->actingAs($this->sdaoA)
        ->post(route('admin.approvers.store'), [
            'first_name' => 'Stale', 'last_name' => 'Adviser', 'email' => 'stale-adviser@nu-lipa.edu.ph',
            'role' => 'adviser', 'organization_id' => $this->org->id,
            'outgoing_adviser' => 'deactivated', 'current_adviser_id' => $this->oldAdviser->id,
        ])
        ->assertSessionHasErrors('adviser');

    expect(User::where('email', 'stale-adviser@nu-lipa.edu.ph')->exists())->toBeFalse();
    expect($fresh->fresh()->deactivated_at)->toBeNull();
});

test('the organization page carries the adviser, their history and the pool', function () {
    swapAdviser(poolAdviser('Pool Pick'));
    $stillInPool = poolAdviser('Still Free');

    $adviser = app(OrganizationDetailData::class)->adviser($this->org);

    expect($adviser['current']['name'])->toBe('Pool Pick')
        ->and($adviser['history'])->toHaveCount(1)
        ->and($adviser['history'][0]['name'])->toBe($this->oldAdviser->name)
        ->and($adviser['history'][0]['ended_by'])->toBe($this->sdaoA->name)
        ->and(collect($adviser['pool'])->pluck('id')->all())->toContain($stillInPool->id, $this->oldAdviser->id);

    $this->actingAs($this->sdaoA)->get(route('admin.organizations.show', $this->org))->assertOk();
});
