<?php

use App\ActivityProposals\StartProposalDraft;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function onCalApprovedActivity(Organization $org, string $name = 'Test Event'): CalendarActivity
{
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
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $name,
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

function onCalInReviewActivity(Organization $org): CalendarActivity
{
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
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Pending Event',
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

test('on-calendar step 1 links the selected Approved CalendarActivity', function () {
    $activity = onCalApprovedActivity($this->computingSociety);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    expect($document->status)->toBe(DocumentStatus::Draft);
    $proposal = $document->activityProposal;
    expect($proposal->calendar_mode)->toBe(ProposalCalendarMode::OnCalendar);
    expect($proposal->calendar_activity_id)->toBe($activity->id);
});

test('on-calendar step 1 does NOT create a new CalendarActivity', function () {
    $countBefore = CalendarActivity::count();
    $activity = onCalApprovedActivity($this->computingSociety);
    $countAfterSeed = CalendarActivity::count();

    $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    expect(CalendarActivity::count())->toBe($countAfterSeed);
});

test('on-calendar rejects a non-Approved (InReview) CalendarActivity', function () {
    $inReviewActivity = onCalInReviewActivity($this->computingSociety);

    expect(fn () => $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $inReviewActivity->id],
    ))->toThrow(ModelNotFoundException::class);
});

test('on-calendar rejects a CalendarActivity from another org', function () {
    $otherOrgActivity = onCalApprovedActivity($this->itGuild, 'IT Guild Event');

    expect(fn () => $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety, // Computing Society student trying to use IT Guild's activity
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $otherOrgActivity->id],
    ))->toThrow(ModelNotFoundException::class);
});

test('on-calendar title is derived from the CalendarActivity name', function () {
    $activity = onCalApprovedActivity($this->computingSociety, 'Annual CS Summit');

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    expect($document->title)->toContain('Annual CS Summit');
    expect($document->title)->toContain('Computing Society');
});
