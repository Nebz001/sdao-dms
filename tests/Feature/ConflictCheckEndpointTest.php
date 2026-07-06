<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->user = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function makeActivityFixture(string $venue, string $date, string $start, string $end, DocumentStatus $status): void
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Fixture Calendar',
        'status' => $status,
        'current_step_position' => $status === DocumentStatus::InReview ? 1 : null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);
    CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Fixture Event',
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

test('conflict-check endpoint returns confirmed conflict for Approved activity', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved);

    $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), [
            'activities' => [[
                'venue' => 'Auditorium',
                'activity_date' => '2026-09-10',
                'start_time' => '14:00',
                'end_time' => '16:00',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.confirmed.0.name', 'Fixture Event')
        ->assertJsonPath('results.0.tentative', []);
});

test('conflict-check endpoint returns tentative conflict for InReview activity', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::InReview);

    $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), [
            'activities' => [[
                'venue' => 'Auditorium',
                'activity_date' => '2026-09-10',
                'start_time' => '14:00',
                'end_time' => '16:00',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.tentative.0.name', 'Fixture Event')
        ->assertJsonPath('results.0.confirmed', []);
});

test('conflict-check endpoint returns no conflicts for non-overlapping activity', function () {
    makeActivityFixture('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved);

    $this->actingAs($this->user)
        ->postJson(route('activity-calendars.conflict-check'), [
            'activities' => [[
                'venue' => 'Auditorium',
                'activity_date' => '2026-09-10',
                'start_time' => '15:00', // touching endpoint — no conflict
                'end_time' => '17:00',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.confirmed', [])
        ->assertJsonPath('results.0.tentative', []);
});
