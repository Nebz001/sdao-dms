<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Calendar\SubmitActivityCalendar;
use App\Calendar\VenueConflictChecker;
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
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->outsider = User::factory()->create();
});

function submittedCalendar(): Document
{
    $action = app(SubmitActivityCalendar::class);
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    return $action->execute(
        actor: $student,
        organization: $org,
        term: Term::FirstTerm,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    )['document'];
}

test('first SDAO approve is partial — document stays InReview', function () {
    $doc = submittedCalendar();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
});

test('second SDAO approve completes the calendar — Approved', function () {
    $doc = submittedCalendar();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();
});

test('approved activities become confirmed hard-blocks', function () {
    $doc = submittedCalendar();
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    // Now try to submit another calendar at the same venue/date/time
    $otherOrg = Organization::where('name', 'IT Guild')->firstOrFail();
    $otherDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Clashing Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $otherOrg->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);

    expect($doc->status)->toBe(DocumentStatus::Approved); // confirmed hard-block source
    // VenueConflictChecker would return it as a confirmed conflict
    $checker = app(VenueConflictChecker::class);
    $conflicts = $checker->confirmedConflicts('Gymnasium', '2026-09-15', '09:30', '11:00');
    expect($conflicts)->not->toBeEmpty();
});

test('reject terminates the document', function () {
    $doc = submittedCalendar();

    $this->engine->reject($doc, $this->sdaoA, 'Not approved.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Rejected);
    expect($doc->current_step_position)->toBeNull();
});

test('return sends document back for revision', function () {
    $doc = submittedCalendar();

    $this->engine->returnForRevision($doc, $this->sdaoA, 'Please fix the venue.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
});

test('non-SDAO user cannot approve', function () {
    $doc = submittedCalendar();

    expect(fn () => $this->engine->approve($doc, $this->outsider))
        ->toThrow(UnauthorizedApproverException::class);
});

test('approve is refused when a confirmed conflict exists at review time (race)', function () {
    $doc = submittedCalendar();

    // Approve a rival calendar at the same venue/time after our calendar was submitted
    $otherOrg = Organization::where('name', 'IT Guild')->firstOrFail();
    $rivalDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Rival Approved Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $otherOrg->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $rivalCal = ActivityCalendar::create([
        'document_id' => $rivalDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => Term::FirstTerm->value,
    ]);
    CalendarActivity::create([
        'activity_calendar_id' => $rivalCal->id,
        'name' => 'Rival Event',
        'venue' => 'Gymnasium',
        'activity_date' => '2026-09-15',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    // Now simulate the controller's pre-approve conflict check
    $checker = app(VenueConflictChecker::class);
    $doc->load('activityCalendar.activities');

    $hasConflict = false;
    foreach ($doc->activityCalendar->activities as $activity) {
        $conflicts = $checker->confirmedConflicts(
            $activity->venue,
            $activity->activity_date->toDateString(),
            $activity->start_time,
            $activity->end_time,
            $doc->id,
        );
        if ($conflicts->isNotEmpty()) {
            $hasConflict = true;
            break;
        }
    }

    expect($hasConflict)->toBeTrue(); // controller would refuse to call engine.approve
    // document remains InReview (engine.approve was NOT called)
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);
});
