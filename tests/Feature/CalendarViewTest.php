<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Term;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->user = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'IT Guild')->firstOrFail();
});

function seedCalendarActivity(string $venue, string $date, string $start, string $end, DocumentStatus $status, string $name = 'Test Event', string $organization = 'IT Guild'): Document
{
    $org = Organization::where('name', $organization)->firstOrFail();
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

    return $doc;
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

function calendarNames($response): array
{
    return collect($response->viewData('page')['props']['activities'])->pluck('name')->sort()->values()->all();
}

test('an officer sees only their own organization on the calendar and can open every item', function () {
    $own = seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'Own Event');
    $other = seedCalendarActivity('Gymnasium', '2026-09-11', '09:00', '11:00', DocumentStatus::Approved, 'Other Org Event', 'Computing Society');

    $response = $this->actingAs($this->user)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarNames($response))->toBe(['Own Event'])
        ->and($response->viewData('page')['props']['visibility'])->toBe('organization');

    $this->actingAs($this->user)->withoutVite()->get(route('activity-calendars.show', $own))->assertOk();
    // A pasted link to another organization's document still gets the 403 fallback.
    $this->actingAs($this->user)->withoutVite()->get(route('activity-calendars.show', $other))->assertForbidden();
});

test('an approver sees only the activities they personally approved and can open every item', function () {
    $adviser = User::where('email', 'adviser-two@nu-lipa.edu.ph')->firstOrFail();
    $approved = seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'Approved By Me');
    $tentative = seedCalendarActivity('Gymnasium', '2026-09-11', '09:00', '11:00', DocumentStatus::InReview, 'Passed By Me');
    $never = seedCalendarActivity('Hall', '2026-09-12', '09:00', '11:00', DocumentStatus::Approved, 'Never Mine');
    calendarApproves($approved, $adviser);
    calendarApproves($tentative, $adviser);

    $response = $this->actingAs($adviser)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarNames($response))->toBe(['Approved By Me', 'Passed By Me'])
        ->and($response->viewData('page')['props']['visibility'])->toBe('approved');

    $this->actingAs($adviser)->withoutVite()->get(route('review.activity-calendars.show', $approved))->assertOk();
    $this->actingAs($adviser)->withoutVite()->get(route('review.activity-calendars.show', $tentative))->assertOk();
    $this->actingAs($adviser)->withoutVite()->get(route('review.activity-calendars.show', $never))->assertForbidden();
});

test('SDAO still sees every organization on the calendar', function () {
    $sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'IT Event');
    seedCalendarActivity('Gymnasium', '2026-09-11', '09:00', '11:00', DocumentStatus::InReview, 'CS Event', 'Computing Society');

    $response = $this->actingAs($sdao)->withoutVite()->get(route('calendar.index'))->assertOk();

    expect(calendarNames($response))->toBe(['CS Event', 'IT Event'])
        ->and($response->viewData('page')['props']['visibility'])->toBe('all');
});

test('an approver who approved nothing sees an empty calendar', function () {
    $dean = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    seedCalendarActivity('Auditorium', '2026-09-10', '13:00', '15:00', DocumentStatus::Approved, 'Not Mine');

    $this->actingAs($dean)->withoutVite()->get(route('calendar.index'))
        ->assertInertia(fn ($page) => $page->has('activities', 0)->where('visibility', 'approved'));
});
