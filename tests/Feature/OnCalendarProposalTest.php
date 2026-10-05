<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->startDraft = app(StartProposalDraft::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society
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
        attachmentFiles: proposalStepOneAttachmentFiles(),
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
        attachmentFiles: proposalStepOneAttachmentFiles(),
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
        attachmentFiles: proposalStepOneAttachmentFiles(),
    ))->toThrow(ModelNotFoundException::class);
});

test('on-calendar rejects a CalendarActivity from another org', function () {
    $otherOrgActivity = onCalApprovedActivity($this->itGuild, 'IT Guild Event');

    expect(fn () => $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety, // Computing Society student trying to use IT Guild's activity
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $otherOrgActivity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    ))->toThrow(ModelNotFoundException::class);
});

test('on-calendar title is derived from the CalendarActivity name', function () {
    $activity = onCalApprovedActivity($this->computingSociety, 'Annual CS Summit');

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    expect($document->title)->toContain('Annual CS Summit');
    expect($document->title)->toContain('Computing Society');
});

function proposalDocumentFor(Organization $org, User $submitter, CalendarActivity $activity, DocumentStatus $status): Document
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

test('starting a new draft against an already-locked activity is blocked', function () {
    $activity = onCalApprovedActivity($this->computingSociety);
    proposalDocumentFor($this->computingSociety, $this->student, $activity, DocumentStatus::InReview);

    expect(fn () => $this->startDraft->execute(
        actor: $this->secretary,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    ))->toThrow(ValidationException::class);
});

test('race: two drafts against the same activity — only the first submit reaches InReview, the second is blocked', function () {
    $activity = onCalApprovedActivity($this->computingSociety);

    $draftA = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $draftB = $this->startDraft->execute(
        actor: $this->secretary,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    // Both drafts coexist — Draft never locks the activity.
    expect($draftA->status)->toBe(DocumentStatus::Draft);
    expect($draftB->status)->toBe(DocumentStatus::Draft);

    $submitAction = app(SubmitActivityProposal::class);

    $submitAction->execute(actor: $this->student, document: $draftA, objectives: 'Goal A');
    $draftA->refresh();
    expect($draftA->status)->toBe(DocumentStatus::InReview);

    expect(fn () => $submitAction->execute(actor: $this->secretary, document: $draftB, objectives: 'Goal B'))
        ->toThrow(ValidationException::class);

    $draftB->refresh();
    expect($draftB->status)->toBe(DocumentStatus::Draft);
});
