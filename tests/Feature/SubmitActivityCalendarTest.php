<?php

use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\ApprovalNotification;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicPeriod;
use App\Support\AcademicYear;
use App\Support\CurrentPeriod;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitActivityCalendar::class);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
});

function calendarPayload(): array
{
    return [
        'activities' => [[
            'name' => 'JS Night',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'description' => 'JavaScript showcase.',
        ]],
    ];
}

function makeApprovedCalendarActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Existing Approved Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function makeInReviewCalendarActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'InReview Calendar',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Existing InReview Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

test('affiliated president can submit an activity calendar', function () {
    $p = calendarPayload();
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['document']->form_type)->toBe(FormType::ActivityCalendar);
    expect($result['document']->current_step_position)->toBe(1);
    expect($result['document']->organization_id)->toBe($this->org->id);
});

test('submission creates an activity calendar and activity rows', function () {
    $p = calendarPayload();
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    $docId = $result['document']->id;
    expect(ActivityCalendar::where('document_id', $docId)->exists())->toBeTrue();
    expect(CalendarActivity::whereHas('calendar', fn ($q) => $q->where('document_id', $docId))->count())->toBe(1);
});

test('submission notifies both SDAO members', function () {
    $p = calendarPayload();
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    $docId = $result['document']->id;
    expect(ApprovalNotification::where('document_id', $docId)->where('user_id', $this->sdaoA->id)->exists())->toBeTrue();
    expect(ApprovalNotification::where('document_id', $docId)->where('user_id', $this->sdaoB->id)->exists())->toBeTrue();
});

test('affiliated secretary (equal partner) can also submit', function () {
    $p = calendarPayload();
    $result = $this->action->execute(
        actor: $this->studentDelta,
        organization: $this->org,
        activities: $p['activities'],
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['document']->submitted_by)->toBe($this->studentDelta->id);
});

test('unaffiliated user cannot submit', function () {
    $outsider = User::factory()->create();

    expect(fn () => $this->action->execute(
        actor: $outsider,
        organization: $this->org,
        activities: calendarPayload()['activities'],
    ))->toThrow(AuthorizationException::class);
});

test('tentative overlap is non-blocking — submission succeeds with warnings', function () {
    makeInReviewCalendarActivity('Gymnasium', '2026-09-15', '09:00', '12:00');

    $p = calendarPayload();
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    // Document IS created (non-blocking)
    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    // And warnings are present
    expect($result['warnings'])->not->toBeEmpty();
    expect($result['warnings'][0]['activity_index'])->toBe(0);
});

test('confirmed hard-block: overlap vs an Approved activity is rejected with no rows created', function () {
    makeApprovedCalendarActivity('Gymnasium', '2026-09-15', '09:00', '12:00');

    $p = calendarPayload();

    expect(fn () => $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    ))->toThrow(ValidationException::class);

    // No Document row was created for the submitter's org
    expect(Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeFalse();
});

test('same venue + date + touching endpoints vs Approved is allowed', function () {
    // Approved activity ends at 09:00 — our new activity starts at 09:00 (touching)
    makeApprovedCalendarActivity('Gymnasium', '2026-09-15', '07:00', '09:00');

    $p = calendarPayload(); // start_time = '09:00'
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['warnings'])->toBeEmpty();
});

test('same date + time + different venue vs Approved is allowed', function () {
    makeApprovedCalendarActivity('Auditorium', '2026-09-15', '09:00', '12:00'); // different venue

    $p = calendarPayload(); // venue = 'Gymnasium'
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    );

    expect($result['document']->status)->toBe(DocumentStatus::InReview);
    expect($result['warnings'])->toBeEmpty();
});

test('intra-calendar self-overlap is a validation error', function () {
    expect(fn () => $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: [
            ['name' => 'Event A', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '09:00', 'end_time' => '12:00'],
            ['name' => 'Event B', 'venue' => 'Gymnasium', 'activity_date' => '2026-09-15', 'start_time' => '11:00', 'end_time' => '14:00'],
        ],
    ))->toThrow(ValidationException::class);
});

// --- One activity calendar per term -------------------------------------

test('a second submission for the same term is blocked while the first is Draft/InReview/Returned', function () {
    $p = calendarPayload();
    $first = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);
    expect($first['document']->status)->toBe(DocumentStatus::InReview);

    expect(fn () => $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    ))->toThrow(ValidationException::class);

    // Returned also blocks — same document, still occupies the slot.
    $this->engine->returnForRevision($first['document'], $this->sdaoA, 'Please add more detail.');
    $first['document']->refresh();
    expect($first['document']->status)->toBe(DocumentStatus::Returned);

    expect(fn () => $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    ))->toThrow(ValidationException::class);
});

test('a second submission is blocked even after the first calendar is approved — Approved is not merely in-flight', function () {
    $p = calendarPayload();
    $first = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);

    $this->engine->approve($first['document'], $this->sdaoA);
    $this->engine->approve($first['document'], $this->sdaoB);
    $first['document']->refresh();
    expect($first['document']->status)->toBe(DocumentStatus::Approved);

    // This is the case that would silently break if the eligibility check
    // reused DocumentStatus::isInFlight() instead of a non-rejected check —
    // isInFlight() excludes Approved, which would wrongly allow this.
    expect(fn () => $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: $p['activities'],
    ))->toThrow(ValidationException::class, 'has already been approved');
});

test('a rejected calendar frees the term — a new submission succeeds', function () {
    $p = calendarPayload();
    $first = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);

    $this->engine->reject($first['document'], $this->sdaoA, 'Incomplete.');
    $first['document']->refresh();
    expect($first['document']->status)->toBe(DocumentStatus::Rejected);

    $second = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);

    expect($second['document']->status)->toBe(DocumentStatus::InReview);
    expect($second['document']->id)->not->toBe($first['document']->id);
});

test('advancing the period to the next term unblocks a previously blocked org', function () {
    $p = calendarPayload();
    $first = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);
    expect($first['document']->status)->toBe(DocumentStatus::InReview);

    $current = CurrentPeriod::get();
    CurrentPeriod::set(new AcademicPeriod($current->academicYear, Term::SecondTerm));

    $second = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: $p['activities']);

    expect($second['document']->status)->toBe(DocumentStatus::InReview);
    expect($second['document']->activityCalendar->term)->toBe(Term::SecondTerm);
});
