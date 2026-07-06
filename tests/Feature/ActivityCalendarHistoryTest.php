<?php

use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\Term;
use App\Enums\TransitionAction;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

test('transitions are recorded for each action', function () {
    $action = app(SubmitActivityCalendar::class);
    $result = $action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        term: Term::FirstTerm,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc = $result['document'];
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    $doc->load('transitions');
    $actions = $doc->transitions->pluck('action');

    expect($actions)->toContain(TransitionAction::Submitted);
    expect($actions)->toContain(TransitionAction::Approved);
    expect($actions)->toContain(TransitionAction::Completed);
    expect($doc->status)->toBe(DocumentStatus::Approved);
});

test('return transition includes the comment', function () {
    $action = app(SubmitActivityCalendar::class);
    $result = $action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        term: Term::FirstTerm,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc = $result['document'];
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please clarify the venue.');
    $doc->refresh()->load('transitions');

    $returnTransition = $doc->transitions->first(fn ($t) => $t->action === TransitionAction::Returned);
    expect($returnTransition)->not->toBeNull();
    expect($returnTransition->comment)->toBe('Please clarify the venue.');
    expect($returnTransition->actor?->id)->toBe($this->sdaoA->id);
});

test('show endpoint returns document with history', function () {
    $action = app(SubmitActivityCalendar::class);
    $result = $action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        term: Term::FirstTerm,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc = $result['document'];

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/show')
            ->has('document')
            ->has('history')
            ->has('calendar')
        );
});
