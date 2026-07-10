<?php

use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Calendar\UpdateActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentTerm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->updateAction = app(UpdateActivityCalendar::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

function returnedCalendar(): Document
{
    $action = app(SubmitActivityCalendar::class);
    $engine = app(ApprovalEngine::class);
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    $result = $action->execute(
        actor: $student,
        organization: $org,
        activities: [[
            'name' => 'Draft Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc = $result['document'];
    $engine->returnForRevision($doc, $sdaoA, 'Please update the venue.');
    $doc->refresh();

    return $doc;
}

test('officer can resubmit a returned calendar', function () {
    $doc = returnedCalendar();

    $result = $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        activities: [[
            'name' => 'Updated Event',
            'venue' => 'Auditorium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['document']->current_step_position)->toBe(1);
});

test('resubmitted calendar resumes at the SDAO step and requires both to re-approve', function () {
    $doc = returnedCalendar();

    $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        activities: [[
            'name' => 'Updated Event',
            'venue' => 'Auditorium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc->refresh();
    // First approval — still InReview
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);

    // Second approval — Approved
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
});

test('activities are replaced on resubmit', function () {
    $doc = returnedCalendar();

    $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        activities: [
            ['name' => 'New Event A', 'venue' => 'Auditorium', 'activity_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '11:00'],
            ['name' => 'New Event B', 'venue' => 'AVR 1', 'activity_date' => '2026-10-02', 'start_time' => '13:00', 'end_time' => '15:00'],
        ],
    );

    $doc->refresh();
    $doc->load('activityCalendar.activities');
    expect($doc->activityCalendar->activities)->toHaveCount(2);
    expect($doc->activityCalendar->activities->pluck('name')->sort()->values()->all())
        ->toBe(['New Event A', 'New Event B']);
});

test('non-submitter cannot resubmit', function () {
    $doc = returnedCalendar();
    $outsider = User::factory()->create();

    expect(fn () => $this->updateAction->execute(
        actor: $outsider,
        document: $doc,
        activities: [['name' => 'X', 'venue' => 'Y', 'activity_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '11:00']],
    ))->toThrow(AuthorizationException::class);
});

test('resubmit retains the term frozen at original submission — even after a global term change', function () {
    CurrentTerm::set(Term::FirstTerm);
    $doc = returnedCalendar();
    $doc->load('activityCalendar');
    expect($doc->activityCalendar->term)->toBe(Term::FirstTerm);

    // Admin changes the global current term AFTER submission but before resubmit.
    CurrentTerm::set(Term::ThirdTerm);

    $this->updateAction->execute(
        actor: $this->studentAlpha,
        document: $doc,
        activities: [[
            'name' => 'Updated Event',
            'venue' => 'Auditorium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );

    $doc->refresh();
    $doc->load('activityCalendar');
    expect($doc->activityCalendar->term)->toBe(Term::FirstTerm);
});
