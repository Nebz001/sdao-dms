<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\CalendarFixtures;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->user = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'IT Guild')->firstOrFail();
});

function calendarButtons($response): array
{
    return collect($response->viewData('page')['props']['activities'])->pluck('can_view_proposal', 'name')->all();
}

function calendarApproves(Document $document, User $approver): void
{
    DocumentTransition::create([
        'document_id' => $document->id,
        'actor_id' => $approver->id,
        'action' => TransitionAction::Approved,
        'from_status' => DocumentStatus::InReview,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => now(),
    ]);
}

function proposalDocumentOf(CalendarActivity $activity): Document
{
    return ActivityProposal::where('calendar_activity_id', $activity->id)->firstOrFail()->document;
}

test('calendar index shows activities of approved on-calendar and off-calendar proposals', function () {
    CalendarFixtures::publishedActivity(['name' => 'On Calendar Event', 'venue' => 'Auditorium'], $this->org);
    CalendarFixtures::offCalendarProposal(DocumentStatus::Approved, ['name' => 'Off Calendar Event'], $this->org);

    $this->actingAs($this->user)
        ->withoutVite()
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/index')
            ->has('activities', 2)
            ->where('activities.0.status', 'approved')
        );
});

test('approving an Activity Calendar through the engine puts nothing on the calendar', function () {
    $engine = app(ApprovalEngine::class);
    $document = Document::factory()->create([
        'form_type' => FormType::ActivityCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $planned = CalendarActivity::factory()->for(
        ActivityCalendar::factory()->for($document)->create(),
        'calendar',
    )->create(['name' => 'Planned Event']);

    $engine->submit($document, $this->user);
    $engine->approve($document->refresh(), User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail());
    $engine->approve($document->refresh(), User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail());

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved);

    $this->actingAs($this->user)->withoutVite()->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activities', 0));
    expect(CalendarActivity::published()->count())->toBe(0);

    // The planned row stays on the approved calendar: the list an on-calendar proposal is filed against.
    expect($planned->calendar->document->status)->toBe(DocumentStatus::Approved);
});

test('a proposal appears only after its final approval, not after a partial one', function () {
    $engine = app(ApprovalEngine::class);
    $adviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $chair = User::where('email', 'chair-it@nu-lipa.edu.ph')->firstOrFail();
    $dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();

    $planned = CalendarFixtures::plannedActivity(DocumentStatus::Approved, ['name' => 'Routed Event', 'venue' => 'Auditorium'], $this->org);
    $proposal = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    ActivityProposal::factory()->create([
        'document_id' => $proposal->id,
        'calendar_activity_id' => $planned->id,
        'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
    ]);

    $publishedCount = fn () => CalendarActivity::published()->count();

    $engine->submit($proposal, $adviser);
    expect($publishedCount())->toBe(0);

    // Adviser, chair, dean, then only the first SDAO member (partial quorum).
    foreach ([$adviser, $chair, $dean, $sdaoA] as $approver) {
        $engine->approve($proposal->refresh(), $approver);
        expect($publishedCount())->toBe(0);
    }

    // Second SDAO member completes the SDAO step; three more steps remain.
    foreach ([$sdaoB, User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail(), User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail()] as $approver) {
        $engine->approve($proposal->refresh(), $approver);
        expect($proposal->refresh()->status)->toBe(DocumentStatus::InReview)
            ->and($publishedCount())->toBe(0);
    }

    $engine->approve($proposal->refresh(), User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail());

    expect($proposal->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($publishedCount())->toBe(1);

    $this->actingAs($this->user)->withoutVite()->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities', 1)
            ->where('activities.0.name', 'Routed Event')
            ->where('activities.0.venue', 'Auditorium')
            ->where('activities.0.document_id', $proposal->id)
        );
});

test('draft, in-routing, returned and rejected proposals never show, nor do planned entries or in-review calendars', function () {
    foreach ([DocumentStatus::Draft, DocumentStatus::InReview, DocumentStatus::Returned, DocumentStatus::Rejected] as $status) {
        CalendarFixtures::offCalendarProposal($status, ['name' => "Off {$status->value}"], $this->org);
        CalendarFixtures::proposalFor(CalendarFixtures::plannedActivity(DocumentStatus::Approved, ['name' => "On {$status->value}"], $this->org), $status);
    }
    CalendarFixtures::plannedActivity(DocumentStatus::InReview, ['name' => 'In review calendar'], $this->org);
    CalendarFixtures::plannedActivity(DocumentStatus::Approved, ['name' => 'Approved calendar only'], $this->org);

    $this->actingAs($this->user)->withoutVite()->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activities', 0));
});

test('everyone sees every activity, but an officer gets the View proposal button only on their own organization', function () {
    $own = CalendarFixtures::publishedActivity(['name' => 'Own Event'], $this->org);
    $other = CalendarFixtures::publishedActivity(['name' => 'Other Org Event'], Organization::where('name', 'Computing Society')->firstOrFail());

    $response = $this->actingAs($this->user)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarButtons($response))->toEqual(['Own Event' => true, 'Other Org Event' => false]);

    $this->actingAs($this->user)->withoutVite()->get(route('activity-proposals.show', proposalDocumentOf($own)))->assertOk();
    $this->actingAs($this->user)->withoutVite()->get(route('activity-proposals.show', proposalDocumentOf($other)))->assertForbidden();
});

test('an approver sees every activity, with the button only where they approved the proposal', function () {
    $adviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $mine = CalendarFixtures::publishedActivity(['name' => 'Approved By Me'], $this->org);
    $never = CalendarFixtures::publishedActivity(['name' => 'Never Mine'], $this->org);
    calendarApproves(proposalDocumentOf($mine), $adviser);

    $response = $this->actingAs($adviser)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarButtons($response))->toEqual(['Approved By Me' => true, 'Never Mine' => false]);

    $this->actingAs($adviser)->withoutVite()->get(route('review.activity-proposals.show', proposalDocumentOf($mine)))->assertOk();
    $this->actingAs($adviser)->withoutVite()->get(route('review.activity-proposals.show', proposalDocumentOf($never)))->assertForbidden();
});

test('SDAO sees every activity with the button on all of them', function () {
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $it = CalendarFixtures::publishedActivity(['name' => 'IT Event'], $this->org);
    CalendarFixtures::offCalendarProposal(DocumentStatus::Approved, ['name' => 'CS Event'], Organization::where('name', 'Computing Society')->firstOrFail());

    $response = $this->actingAs($sdao)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarButtons($response))->toEqual(['IT Event' => true, 'CS Event' => true]);

    $this->actingAs($sdao)->withoutVite()->get(route('review.activity-proposals.show', proposalDocumentOf($it)))->assertOk();
});
