<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\ActivityProposals\UpdateProposalDraft;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->updateDraft = app(UpdateProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function draftOffCalendarData(): array
{
    return [
        'title' => 'Draft Test Activity',
        'venue' => 'Room 200',
        'activity_date' => '2026-12-01',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'term' => 'first_term',
    ];
}

test('step 1 creates a Draft document — not yet InReview', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    expect($document->status)->toBe(DocumentStatus::Draft);
    expect($document->form_type)->toBe(FormType::ActivityProposal);
    expect($document->workflow_template_id)->toBeNull();
    expect($document->current_step_position)->toBeNull();
    expect($document->variant)->toBeNull();
});

test('step 1 creates the ActivityProposal record with form_step = 2', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $proposal = $document->activityProposal;

    expect($proposal)->not->toBeNull();
    expect($proposal->form_step)->toBe(2);
    expect($proposal->calendar_mode)->toBe(ProposalCalendarMode::OffCalendar);
    expect($proposal->objectives)->toBeNull();
    expect($proposal->narrative)->toBeNull();
});

test('off-calendar step 1 creates the ActivityCalendar container and CalendarActivity', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $proposal = $document->activityProposal;
    expect($proposal->calendar_activity_id)->not->toBeNull();

    $activity = $proposal->calendarActivity;
    expect($activity->venue)->toBe('Room 200');
    expect($activity->activity_date->toDateString())->toBe('2026-12-01');

    // ActivityCalendar container points to the PROPOSAL document
    expect($activity->calendar->document_id)->toBe($document->id);
});

test('auto-save updates narrative fields and keeps Draft status', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $document->load('activityProposal');

    $this->updateDraft->execute($this->student, $document, [
        'objectives' => 'My objectives',
        'narrative' => 'My narrative',
    ]);

    $document->refresh()->load('activityProposal');

    expect($document->status)->toBe(DocumentStatus::Draft);
    expect($document->activityProposal->objectives)->toBe('My objectives');
    expect($document->activityProposal->narrative)->toBe('My narrative');
    expect($document->workflow_template_id)->toBeNull();
});

test('auto-save by another user throws AuthorizationException', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $document->load('activityProposal');
    $otherUser = User::factory()->create();

    expect(fn () => $this->updateDraft->execute($otherUser, $document, ['objectives' => 'Sneaky']))
        ->toThrow(AuthorizationException::class);
});

test('document enters chain only after step-2 submit', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $document->load('activityProposal');
    $this->updateDraft->execute($this->student, $document, [
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
    ]);

    // Still Draft after auto-save
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft);

    $result = $this->submitProposal->execute(
        actor: $this->student,
        document: $document,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['document']->workflow_template_id)->not->toBeNull();
    expect($result['document']->current_step_position)->toBe(1);
});

test('saved narrative is available for resume via the model', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: draftOffCalendarData(),
    );

    $document->load('activityProposal');
    $this->updateDraft->execute($this->student, $document, [
        'objectives' => 'Remembered objectives',
    ]);

    $loaded = Document::with('activityProposal')->find($document->id);
    expect($loaded->activityProposal->objectives)->toBe('Remembered objectives');
    expect($loaded->status)->toBe(DocumentStatus::Draft);
});
