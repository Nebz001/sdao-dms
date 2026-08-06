<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Sdg;
use App\Enums\TransitionAction;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Printing\ActivityCalendarForm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Covers App\Printing\ActivityCalendarForm::data(): the title (academic
 * year + term label), the STATUS column deriving from the real
 * DocumentStatus rather than the source spreadsheet's literal "100%", and
 * that RSO Name repeats identically down every row (one Document = one
 * org's calendar submission, not the sample's aggregate master log).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

/**
 * @param  array<int, array<string, mixed>>  $activities
 */
function activityCalendarPrintDocument(Organization $org, DocumentStatus $status, array $activities): Document
{
    $doc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Print Test Calendar',
        'status' => $status,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);

    $calendar = ActivityCalendar::create([
        'document_id' => $doc->id,
        'academic_year' => '2025-2026',
        'term' => 'second_term',
    ]);

    foreach ($activities as $activity) {
        CalendarActivity::create(array_merge([
            'activity_calendar_id' => $calendar->id,
            'name' => 'Test Activity',
            'venue' => 'Gym',
            'activity_date' => '2026-11-01',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'sdg' => Sdg::QualityEducation->value,
            'participant_program_assigned' => 'All programs',
            'budget' => 1000,
        ], $activity));
    }

    return $doc;
}

function dataForCalendar(Document $document): array
{
    $form = app(ActivityCalendarForm::class);
    $document->load($form->eagerLoads());

    return $form->data($document);
}

test('the title includes the academic year and the term label', function () {
    $doc = activityCalendarPrintDocument($this->org, DocumentStatus::Approved, [[]]);

    $data = dataForCalendar($doc);

    expect($data['title'])->toBe('CALENDAR OF ACTIVITIES AY. 2025-2026 (2nd Term)');
});

test('RSO Name repeats identically down every row for a multi-activity calendar', function () {
    $doc = activityCalendarPrintDocument($this->org, DocumentStatus::Approved, [
        ['name' => 'Activity One'],
        ['name' => 'Activity Two'],
        ['name' => 'Activity Three'],
    ]);

    $data = dataForCalendar($doc);

    expect($data['rows'])->toHaveCount(3);
    expect(collect($data['rows'])->pluck('rso_name')->unique()->all())->toBe(['Computing Society']);
});

test('STATUS prints the real DocumentStatus label, not the source spreadsheet\'s literal 100%', function () {
    foreach ([
        DocumentStatus::Draft->value => 'Draft',
        DocumentStatus::InReview->value => 'In Review',
        DocumentStatus::Returned->value => 'Returned',
        DocumentStatus::Approved->value => 'Approved',
        DocumentStatus::Rejected->value => 'Rejected',
    ] as $status => $expectedLabel) {
        $doc = activityCalendarPrintDocument($this->org, DocumentStatus::from($status), [[]]);
        $data = dataForCalendar($doc);

        expect($data['rows'][0]['status'])->toBe($expectedLabel);
    }
});

test('each row maps its own SDG, venue, budget, and participant/program assigned', function () {
    $doc = activityCalendarPrintDocument($this->org, DocumentStatus::Approved, [
        ['name' => 'Activity One', 'sdg' => Sdg::ClimateAction->value, 'venue' => 'Room 101', 'budget' => 2500, 'participant_program_assigned' => 'BS CS only'],
    ]);

    $data = dataForCalendar($doc);
    $row = $data['rows'][0];

    expect($row['activity_name'])->toBe('Activity One');
    expect($row['sdg'])->toBe('SDG 13 — Climate Action');
    expect($row['venue'])->toBe('Room 101');
    expect($row['budget'])->toBe('2,500.00');
    expect($row['participant_program_assigned'])->toBe('BS CS only');
});

test('Date Received uses the latest submitted-or-resubmitted transition, not the first one', function () {
    $doc = activityCalendarPrintDocument($this->org, DocumentStatus::Approved, [[]]);

    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Submitted,
        'from_status' => DocumentStatus::Draft,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => '2026-07-01 08:00:00',
    ]);
    DocumentTransition::create([
        'document_id' => $doc->id,
        'actor_id' => $this->studentAlpha->id,
        'action' => TransitionAction::Resubmitted,
        'from_status' => DocumentStatus::Returned,
        'to_status' => DocumentStatus::InReview,
        'step_position' => 1,
        'created_at' => '2026-07-10 08:00:00',
    ]);

    $data = dataForCalendar($doc);

    expect($data['rows'][0]['date_received'])->toBe('07/10/2026');
});

test('the rendered Blade keeps the "DATE RECIEVED" header misspelled verbatim, matching the source spreadsheet', function () {
    $doc = activityCalendarPrintDocument($this->org, DocumentStatus::Approved, [[]]);
    $form = app(ActivityCalendarForm::class);
    $doc->load($form->eagerLoads());

    $html = view($form->view(), $form->data($doc))->render();

    expect($html)->toContain('DATE RECIEVED')->not->toContain('DATE RECEIVED');
});
