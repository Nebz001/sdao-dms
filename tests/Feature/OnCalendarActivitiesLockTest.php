<?php

use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * ActivityProposalController::onCalendarActivities() — the on-calendar
 * picker must exclude any activity already locked by another active
 * proposal (App\ActivityProposals\OnCalendarActivityLockChecker). This is
 * the convenience layer only; the authoritative guards live in
 * StartProposalDraft and SubmitActivityProposal (see OnCalendarProposalTest).
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // president, Computing Society
});

function lockTestApprovedActivity(Organization $org, string $name = 'Test Event'): CalendarActivity
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

function lockTestProposalFor(Organization $org, User $submitter, CalendarActivity $activity, DocumentStatus $status): Document
{
    $document = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => null,
        'title' => 'Existing Proposal',
        'status' => $status,
        'current_step_position' => in_array($status, [DocumentStatus::InReview, DocumentStatus::Returned], true) ? 1 : null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);

    ActivityProposal::create([
        'document_id' => $document->id,
        'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
        'calendar_activity_id' => $activity->id,
        'title' => $activity->name,
        'activity_nature' => ActivityNature::CoCurricular->value,
        'activity_type' => ActivityType::Competition->value,
        'target_sdg' => [Sdg::QualityEducation->value],
        'form_step' => 2,
    ]);

    return $document;
}

test('an activity with an active proposal does not appear in the on-calendar picker', function (DocumentStatus $status) {
    $activity = lockTestApprovedActivity($this->computingSociety);
    lockTestProposalFor($this->computingSociety, $this->studentAlpha, $activity, $status);

    $response = $this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.on-calendar-activities'));

    $response->assertOk();
    $ids = collect($response->json('activities'))->pluck('id');
    expect($ids)->not->toContain($activity->id);
})->with([
    'in review' => [DocumentStatus::InReview],
    'returned' => [DocumentStatus::Returned],
    'approved' => [DocumentStatus::Approved],
]);

test('once the blocking proposal is Rejected, the activity becomes selectable again', function () {
    $activity = lockTestApprovedActivity($this->computingSociety);
    $blocking = lockTestProposalFor($this->computingSociety, $this->studentAlpha, $activity, DocumentStatus::InReview);

    $before = collect($this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.on-calendar-activities'))
        ->json('activities'))->pluck('id');
    expect($before)->not->toContain($activity->id);

    $blocking->update(['status' => DocumentStatus::Rejected]);

    $after = collect($this->actingAs($this->studentAlpha)
        ->getJson(route('activity-proposals.on-calendar-activities'))
        ->json('activities'))->pluck('id');
    expect($after)->toContain($activity->id);
});
