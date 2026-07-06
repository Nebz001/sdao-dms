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
    $this->org = Organization::where('name', 'IT Guild')->firstOrFail();
});

function seedCalendarActivity(string $venue, string $date, string $start, string $end, DocumentStatus $status, string $name = 'Test Event'): void
{
    $org = Organization::where('name', 'IT Guild')->firstOrFail();
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => "Calendar View Test {$status->value}",
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
        'name' => $name,
        'venue' => $venue,
        'activity_date' => $date,
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

test('calendar index shows confirmed and tentative activities', function () {
    seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'Approved Event');
    seedCalendarActivity('Gymnasium', '2026-09-11', '09:00', '11:00', DocumentStatus::InReview, 'Tentative Event');

    $this->actingAs($this->user)
        ->withoutVite()
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendar/index')
            ->has('activities', 2)
        );
});

test('calendar index excludes rejected and returned documents', function () {
    seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Rejected, 'Rejected Event');
    seedCalendarActivity('Gymnasium', '2026-09-11', '09:00', '11:00', DocumentStatus::Returned, 'Returned Event');

    $this->actingAs($this->user)
        ->withoutVite()
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities', 0)
        );
});

test('calendar index includes activity status in response', function () {
    seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'Approved Event');

    $this->actingAs($this->user)
        ->withoutVite()
        ->get(route('calendar.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities', 1)
            ->where('activities.0.status', 'approved')
            ->where('activities.0.name', 'Approved Event')
        );
});
