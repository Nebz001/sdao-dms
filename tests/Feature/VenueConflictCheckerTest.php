<?php

use App\Calendar\VenueConflictChecker;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Helper: create an approved activity at the given venue/date/time.
 * Bypasses the engine to set status directly (unit scope).
 */
function approvedActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::firstOrFail();
    $document = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Test Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $document->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Existing Activity',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

function inReviewActivity(string $venue, string $date, string $start, string $end): CalendarActivity
{
    $org = Organization::firstOrFail();
    $document = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Test Calendar InReview',
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $document->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'In Review Activity',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->checker = app(VenueConflictChecker::class);
});

// ── Confirmed conflict matrix ─────────────────────────────────────────────────

test('same venue + date + overlapping time is a confirmed conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '14:00', '16:00');

    expect($conflicts)->toHaveCount(1);
});

test('same venue + date + touching endpoints is NOT a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '10:00', '12:00');

    // Starts exactly when the other ends — strict inequality means no overlap
    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '12:00', '14:00');

    expect($conflicts)->toBeEmpty();
});

test('same date + time + different venue is NOT a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $conflicts = $this->checker->confirmedConflicts('Gymnasium', '2026-09-10', '13:00', '15:00');

    expect($conflicts)->toBeEmpty();
});

test('same venue + overlapping time + different date is NOT a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-11', '13:00', '15:00');

    expect($conflicts)->toBeEmpty();
});

test('one activity fully contained inside another is a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '09:00', '18:00');

    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '11:00', '13:00');

    expect($conflicts)->toHaveCount(1);
});

test('identical range is a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '13:00', '15:00');

    expect($conflicts)->toHaveCount(1);
});

test('activity just before (ends when new one starts) is NOT a conflict', function () {
    approvedActivity('Auditorium', '2026-09-10', '10:00', '13:00');

    // New activity starts at 13:00 = touching endpoint, not an overlap
    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '13:00', '15:00');

    expect($conflicts)->toBeEmpty();
});

// ── Status filter ─────────────────────────────────────────────────────────────

test('only Approved documents count as confirmed conflicts', function () {
    inReviewActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $confirmed = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '13:00', '15:00');

    expect($confirmed)->toBeEmpty(); // InReview is NOT a hard block
});

test('only InReview documents count as tentative conflicts', function () {
    approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $tentative = $this->checker->tentativeConflicts('Auditorium', '2026-09-10', '13:00', '15:00');

    expect($tentative)->toBeEmpty(); // Approved is NOT a tentative warning
});

test('InReview activity is a tentative conflict', function () {
    inReviewActivity('Auditorium', '2026-09-10', '13:00', '15:00');

    $tentative = $this->checker->tentativeConflicts('Auditorium', '2026-09-10', '14:00', '16:00');

    expect($tentative)->toHaveCount(1);
});

// ── excludeDocumentId ─────────────────────────────────────────────────────────

test('excludeDocumentId excludes the document own activities from the check', function () {
    $existing = approvedActivity('Auditorium', '2026-09-10', '13:00', '15:00');
    $ownDocId = $existing->calendar->document_id;

    $conflicts = $this->checker->confirmedConflicts('Auditorium', '2026-09-10', '13:00', '15:00', $ownDocId);

    expect($conflicts)->toBeEmpty();
});
