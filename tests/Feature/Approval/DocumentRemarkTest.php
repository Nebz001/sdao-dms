<?php

use App\Approval\AddDocumentRemark;
use App\Approval\ApprovalEngine;
use App\Approval\DocumentViewData;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentRemark;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DocumentOutcomeNotification;
use App\Notifications\DocumentRemarkNotification;
use App\Organizations\OrganizationMembershipService;
use Carbon\CarbonImmutable;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->withoutVite();
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->officer = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->otherAdviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $this->otherChair = User::where('email', 'chair-it@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * A regular-school, on-calendar proposal submitted into review: adviser (1),
 * program chair (2), dean (3), SDAO (4), then the three directors.
 */
function remarkProposal(Organization $org, User $submitter): Document
{
    $doc = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar->value,
        'title' => 'Remark Proposal',
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
        'title' => 'Remark Proposal', 'activity_nature' => 'co_curricular', 'activity_type' => 'competition',
        'partner_organizations' => [], 'target_sdg' => ['life_on_land'], 'objectives' => 'Goal',
        'activity_description' => 'Description', 'criteria_mechanics' => 'Criteria', 'program_flow' => 'Flow',
        'expenses' => 'Venue', 'proposed_budget' => 5000, 'budget_source' => 'rso_fund', 'form_step' => 2,
        'president_name' => null,
    ]);
    app(ApprovalEngine::class)->submit($doc, $submitter);

    return $doc->refresh();
}

/** A renewal waiting on SDAO (a short chain with no step before it). */
function remarkRenewal(Organization $org, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRenewal,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    app(ApprovalEngine::class)->submit($doc, $submitter);

    return $doc->refresh();
}

function canRemark(User $user, Document $document): bool
{
    return Gate::forUser($user)->allows('remark', $document->fresh());
}

// ── Policy ───────────────────────────────────────────────────────────────

test('an approver whose step has passed can remark; the current and future steps cannot', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $this->engine->approve($doc->refresh(), $this->chair);
    // Now at the dean (3).

    expect(canRemark($this->adviser, $doc))->toBeTrue()
        ->and(canRemark($this->chair, $doc))->toBeTrue()
        ->and(canRemark($this->dean, $doc))->toBeFalse()
        ->and(canRemark($this->asstDirector, $doc))->toBeFalse();
});

test('an approver who has not reached their step yet cannot remark', function () {
    $doc = remarkProposal($this->org, $this->officer);

    // At the adviser (1): nobody has passed anything yet.
    expect(canRemark($this->adviser, $doc))->toBeFalse()
        ->and(canRemark($this->chair, $doc))->toBeFalse()
        ->and(canRemark($this->dean, $doc))->toBeFalse();
});

test('an approver from another organization\'s chain cannot remark', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $this->engine->approve($doc->refresh(), $this->chair);

    expect(canRemark($this->otherAdviser, $doc))->toBeFalse()
        ->and(canRemark($this->otherChair, $doc))->toBeFalse();
});

test('officers can never remark, whatever the document state', function () {
    $doc = remarkProposal($this->org, $this->officer);
    expect(canRemark($this->officer, $doc))->toBeFalse();

    $this->engine->approve($doc, $this->adviser);
    expect(canRemark($this->officer, $doc))->toBeFalse();

    $doc->forceFill(['status' => DocumentStatus::Approved, 'current_step_position' => null])->save();
    expect(canRemark($this->officer, $doc))->toBeFalse();
});

test('SDAO can remark at any stage after submission, but not on a draft', function () {
    $doc = remarkProposal($this->org, $this->officer);

    // In review, though SDAO's own step (4) has not been reached.
    expect(canRemark($this->sdaoA, $doc))->toBeTrue();

    $draft = Document::factory()->create(['organization_id' => $this->org->id, 'status' => DocumentStatus::Draft]);
    expect(canRemark($this->sdaoA, $draft))->toBeFalse();
});

test('after a terminal state only SDAO can remark', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $this->engine->approve($doc->refresh(), $this->chair);
    expect(canRemark($this->adviser, $doc))->toBeTrue();

    $doc->forceFill(['status' => DocumentStatus::Approved, 'current_step_position' => null])->save();
    expect(canRemark($this->adviser, $doc))->toBeFalse()
        ->and(canRemark($this->chair, $doc))->toBeFalse()
        ->and(canRemark($this->sdaoA, $doc))->toBeTrue();

    $doc->forceFill(['status' => DocumentStatus::Rejected])->save();
    expect(canRemark($this->adviser, $doc))->toBeFalse()
        ->and(canRemark($this->sdaoA, $doc))->toBeTrue();
});

test('while a document is returned, approvers below the returner can remark but the returner cannot', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $this->engine->returnForRevision($doc->refresh(), $this->chair, 'Fix the budget.');

    expect($doc->fresh()->status)->toBe(DocumentStatus::Returned)
        ->and(canRemark($this->adviser, $doc))->toBeTrue()
        ->and(canRemark($this->chair, $doc))->toBeFalse();
});

// ── Approve with remarks ────────────────────────────────────────────────

test('approving with remarks stores them as the comment on the Approved transition, like return and reject do', function () {
    $doc = remarkRenewal($this->org, $this->officer);

    $this->actingAs($this->sdaoA)
        ->post(route('review.renewals.approve', $doc), ['comment' => 'Documents are complete.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $transition = DocumentTransition::where('document_id', $doc->id)->where('action', 'approved')->firstOrFail();
    expect($transition->comment)->toBe('Documents are complete.');
});

test('approving without remarks still works and stores no comment', function () {
    $doc = remarkRenewal($this->org, $this->officer);

    $this->actingAs($this->sdaoA)->post(route('review.renewals.approve', $doc), ['comment' => ''])->assertSessionHasNoErrors();

    expect(DocumentTransition::where('document_id', $doc->id)->where('action', 'approved')->value('comment'))->toBeNull();
});

test('approve remarks work on a long-chain document too, and are capped at 1000 characters', function () {
    $doc = remarkProposal($this->org, $this->officer);

    $this->actingAs($this->adviser)
        ->post(route('review.activity-proposals.approve', $doc), ['comment' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('comment');
    expect($doc->fresh()->current_step_position)->toBe(1);

    $this->actingAs($this->adviser)
        ->post(route('review.activity-proposals.approve', $doc), ['comment' => 'Good to go.'])
        ->assertSessionHasNoErrors();

    $doc->refresh();
    expect($doc->current_step_position)->toBe(2)
        ->and(DocumentTransition::where('document_id', $doc->id)->where('action', 'approved')->value('comment'))->toBe('Good to go.');
});

// ── Add remark ──────────────────────────────────────────────────────────

test('an approver who has already approved can add a remark; it changes no state and writes no transition', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $before = DocumentTransition::where('document_id', $doc->id)->count();

    $this->actingAs($this->adviser)
        ->post(route('documents.remarks.store', $doc), ['body' => 'Please double check the venue.'])
        ->assertRedirect()
        ->assertSessionHas('flash')
        ->assertSessionHasNoErrors();

    $remark = DocumentRemark::where('document_id', $doc->id)->firstOrFail();
    expect($remark->body)->toBe('Please double check the venue.')
        ->and($remark->user_id)->toBe($this->adviser->id)
        ->and(DocumentTransition::where('document_id', $doc->id)->count())->toBe($before)
        ->and($doc->fresh()->status)->toBe(DocumentStatus::InReview)
        ->and($doc->fresh()->current_step_position)->toBe(2);
});

test('the server refuses a remark from anyone the policy denies, even though they can view the document', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    // The chair holds the document right now: they can view and review it, but not remark.

    $this->actingAs($this->chair)->post(route('documents.remarks.store', $doc), ['body' => 'Hello'])->assertForbidden();
    $this->actingAs($this->officer)->post(route('documents.remarks.store', $doc), ['body' => 'Hello'])->assertForbidden();
    $this->actingAs($this->otherAdviser)->post(route('documents.remarks.store', $doc), ['body' => 'Hello'])->assertForbidden();
    $this->actingAs($this->dean)->post(route('documents.remarks.store', $doc), ['body' => 'Hello'])->assertForbidden();

    expect(DocumentRemark::count())->toBe(0);
});

test('an unauthorized user gets 403 rather than a validation error', function () {
    $doc = remarkProposal($this->org, $this->officer);

    $this->actingAs($this->officer)->post(route('documents.remarks.store', $doc), ['body' => ''])->assertForbidden();
});

test('a remark is required, trimmed of nothing, and limited to 1000 characters', function () {
    $doc = remarkProposal($this->org, $this->officer);

    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => ''])->assertSessionHasErrors('body');
    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => str_repeat('a', 1001)])->assertSessionHasErrors('body');
    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => str_repeat('a', 1000)])->assertSessionHasNoErrors();

    expect(DocumentRemark::count())->toBe(1);
});

test('SDAO can still remark on an approved document', function () {
    $doc = remarkRenewal($this->org, $this->officer);
    $doc->forceFill(['status' => DocumentStatus::Approved, 'current_step_position' => null])->save();

    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => 'Filed.'])->assertSessionHasNoErrors();

    expect(DocumentRemark::where('document_id', $doc->id)->count())->toBe(1);
});

test('adding remarks is throttled', function () {
    $doc = remarkRenewal($this->org, $this->officer);

    foreach (range(1, 10) as $i) {
        $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => "Remark {$i}"])->assertRedirect();
    }

    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => 'One too many'])->assertStatus(429);
});

test('remarks are append-only: there is no route to edit or delete one', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'remarks'));

    expect($routes->map(fn ($r) => $r->methods()[0])->values()->all())->toBe(['POST']);
});

// ── Notifications ───────────────────────────────────────────────────────

test('adding a remark notifies the organization\'s officers, not the author, and says it is a remark', function () {
    Notification::fake();
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    Notification::fake();

    $this->actingAs($this->adviser)->post(route('documents.remarks.store', $doc), ['body' => 'Bring a printed copy.']);

    Notification::assertSentTo($this->officer, DocumentRemarkNotification::class, function (DocumentRemarkNotification $n) use ($doc) {
        $data = $n->toArray($this->officer);
        $mail = $n->toMail($this->officer)->render();

        return $n->via($this->officer) === ['mail', 'database']
            && $data['kind'] === 'document_remark'
            && str_contains($data['title'], 'remark')
            && $data['url'] === route('activity-proposals.show', $doc, absolute: false)
            && str_contains($mail, 'Bring a printed copy.')
            && str_contains($mail, 'does not change the status');
    });
    Notification::assertNotSentTo($this->adviser, DocumentRemarkNotification::class);
    Notification::assertNotSentTo($this->officer, DocumentOutcomeNotification::class);
});

test('every active officer of the organization is notified', function () {
    Notification::fake();
    $doc = remarkRenewal($this->org, $this->officer);

    $this->actingAs($this->sdaoA)->post(route('documents.remarks.store', $doc), ['body' => 'Noted.']);

    $officers = app(OrganizationMembershipService::class)->activeOfficersFor($this->org);
    expect($officers)->not->toBeEmpty();
    foreach ($officers as $officer) {
        Notification::assertSentTo($officer, DocumentRemarkNotification::class);
    }
});

test('a rejected remark sends nothing', function () {
    $doc = remarkProposal($this->org, $this->officer);
    Notification::fake();

    $this->actingAs($this->officer)->post(route('documents.remarks.store', $doc), ['body' => 'Hi']);

    Notification::assertNothingSent();
});

// ── Timeline ────────────────────────────────────────────────────────────

test('remarks merge into the history in time order with transitions, newest first', function () {
    $start = CarbonImmutable::parse('2026-10-01 02:00:00', 'UTC');
    $this->travelTo($start);
    $doc = remarkProposal($this->org, $this->officer);               // submitted  02:00
    $this->travelTo($start->addMinutes(10));
    $this->engine->approve($doc, $this->adviser);                      // approved + advanced 02:10
    $this->travelTo($start->addMinutes(20));
    app(AddDocumentRemark::class)->execute($doc, $this->adviser, 'First remark');  // 02:20
    $this->travelTo($start->addMinutes(30));
    $this->engine->returnForRevision($doc->refresh(), $this->chair, 'Fix it');                   // returned 02:30
    $this->travelTo($start->addMinutes(40));
    app(AddDocumentRemark::class)->execute($doc, $this->sdaoA, 'Second remark');   // 02:40

    $history = app(DocumentViewData::class)->for($doc->fresh(), $this->sdaoA, 'x', [])['history'];

    expect(collect($history)->map(fn ($e) => $e['kind'].':'.$e['action'])->all())->toBe([
        'remark:remark',
        'transition:returned',
        'remark:remark',
        'transition:advanced',
        'transition:approved',
        'transition:submitted',
    ]);
});

test('on an exact timestamp tie the remark is listed above the event it follows, deterministically', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 02:00:00', 'UTC'));
    $doc = remarkProposal($this->org, $this->officer);
    app(AddDocumentRemark::class)->execute($doc, $this->sdaoA, 'Same second');

    $history = app(DocumentViewData::class)->for($doc->fresh(), $this->sdaoA, 'x', [])['history'];

    expect($history[0]['kind'])->toBe('remark')
        ->and($history[0]['key'])->not->toBe($history[1]['key']);
});

test('a remark shows the author\'s first and last name and the role they hold on the document', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);
    $this->adviser->forceFill(['first_name' => 'Ada', 'last_name' => 'Lovelace'])->save();
    app(AddDocumentRemark::class)->execute($doc, $this->adviser, 'Note');
    app(AddDocumentRemark::class)->execute($doc, $this->sdaoA, 'SDAO note');

    $remarks = collect(app(DocumentViewData::class)->for($doc->fresh(), $this->sdaoA, 'x', [])['history'])->where('kind', 'remark')->values();

    expect($remarks[1]['actor'])->toBe(['name' => 'Ada Lovelace', 'role' => 'Adviser'])
        ->and($remarks[0]['actor']['role'])->toBe('SDAO Member');
});

test('officers see remarks on their own document page but get no add-remark box', function () {
    $doc = remarkProposal($this->org, $this->officer);
    app(AddDocumentRemark::class)->execute($doc, $this->sdaoA, 'Visible to the org');

    $this->actingAs($this->officer)
        ->get(route('activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view.remark.canAdd', false)
            ->where('view.history.0.kind', 'remark')
            ->where('view.history.0.comment', 'Visible to the org'));
});

test('an approver who may remark is offered the box on the review page', function () {
    $doc = remarkProposal($this->org, $this->officer);
    $this->engine->approve($doc, $this->adviser);

    $this->actingAs($this->adviser)
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view.remark.canAdd', true)
            ->where('view.remark.maxLength', 1000)
            ->where('view.remark.url', route('documents.remarks.store', $doc, absolute: false)));

    $this->actingAs($this->chair)
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page->where('view.remark.canAdd', false));
});
