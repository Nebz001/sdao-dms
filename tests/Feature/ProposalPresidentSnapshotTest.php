<?php

use App\ActivityProposals\ResubmitActivityProposal;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\ActivityNature;
use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OfficerPosition;
use App\Enums\ProposalCalendarMode;
use App\Enums\ProposalVariant;
use App\Enums\Sdg;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\OrganizationMembershipService;
use App\Printing\ActivityProposalForm;
use App\Support\AcademicYear;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The core historical-accuracy regression the officer-history feature was
 * built to fix: App\Printing\ActivityProposalForm's "Prepared by" line used
 * to LIVE-query the org's currently-active president every time the PDF
 * rendered, so reprinting an old proposal after officer turnover showed
 * whoever holds the office TODAY, not who held it when the proposal was
 * ACTUALLY submitted. It now reads a value snapshotted once at submission
 * (App\ActivityProposals\SubmitActivityProposal), which must survive an
 * officer turnover happening afterward.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->resubmitProposal = app(ResubmitActivityProposal::class);
    $this->engine = app(ApprovalEngine::class);
    $this->bindAction = app(BindOrganizationOfficer::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // President at seed time
    $this->adviserOne = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function presidentSnapshotApprovedActivity(Organization $org): CalendarActivity
{
    $calendarDoc = Document::create([
        'form_type' => FormType::ActivityCalendar,
        'variant' => null,
        'title' => 'Backing Calendar',
        'status' => DocumentStatus::Approved,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $calendar = ActivityCalendar::create([
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $calendar->id,
        'name' => 'Snapshot Test Activity',
        'venue' => 'Snapshot Hall',
        'activity_date' => '2026-12-05',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

function dataForSnapshotProposal(Document $document): array
{
    $form = app(ActivityProposalForm::class);
    $document->load($form->eagerLoads());

    return $form->data($document);
}

test('the printed proposal keeps showing the ORIGINAL president after officer turnover, not the incumbent', function () {
    $activity = presidentSnapshotApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Overall goal text',
    );

    expect($doc->activityProposal->fresh()->president_name)->toBe('Student Alpha');

    // Officer turnover — the org's active president is now someone else.
    $newPresident = User::factory()->create(['account_status' => 'verified']);
    $this->bindAction->execute($this->adviserOne, $this->org, $newPresident, OfficerPosition::President);

    $liveMembership = app(OrganizationMembershipService::class)->activePresidentFor($this->org);
    expect($liveMembership->id)->toBe($newPresident->id);
    expect($liveMembership->name)->not->toBe('Student Alpha');

    // Reprint AFTER the turnover — must still show the original submitter's
    // president, not the incumbent.
    $data = dataForSnapshotProposal($doc->fresh());
    expect($data['prepared_by_president'])->toBe('Student Alpha');
    expect($data['prepared_by_president'])->not->toBe($newPresident->name);
});

test('the snapshot survives a return-for-revision → resubmit cycle untouched', function () {
    $activity = presidentSnapshotApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Overall goal text',
    );

    expect($doc->activityProposal->fresh()->president_name)->toBe('Student Alpha');

    $this->engine->returnForRevision($doc, $this->adviserOne, 'Please revise the objectives.');
    $doc->refresh();

    $this->resubmitProposal->execute($this->studentAlpha, $doc, [
        'objectives' => 'Revised overall goal text',
        'activity_description' => 'Activity description',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'expense_items' => null,
        'responsible_persons' => ['Someone'],
        'proposed_budget' => '5000',
    ]);

    expect($doc->activityProposal->fresh()->president_name)->toBe('Student Alpha');

    $data = dataForSnapshotProposal($doc->fresh());
    expect($data['prepared_by_president'])->toBe('Student Alpha');
});

test('a proposal submitted while the presidency is vacant stores a null snapshot and prints blank', function () {
    $studentDelta = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail(); // Secretary, Computing Society

    // Vacate the presidency — Delta (Secretary) remains the only active
    // officer, so she can still submit, but there is no president to snapshot.
    $membershipService = app(OrganizationMembershipService::class);
    $membershipService->closeActiveHolders($this->org, OfficerPosition::President);
    expect($membershipService->activePresidentFor($this->org))->toBeNull();

    $activity = presidentSnapshotApprovedActivity($this->org);

    $draft = $this->startDraft->execute(
        actor: $studentDelta,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    ['document' => $doc] = $this->submitProposal->execute(
        actor: $studentDelta,
        document: $draft,
        objectives: 'Overall goal text',
    );

    expect($doc->activityProposal->fresh()->president_name)->toBeNull();

    $data = dataForSnapshotProposal($doc->fresh());
    expect($data['prepared_by_president'])->toBeNull();
});

test('prepared_by_president prints blank for a null snapshot even when the org currently HAS an active president', function () {
    // Proves the print path reads the stored column, never a live fallback —
    // deliberately construct a proposal with a null snapshot for an org
    // that DOES have an active president right now.
    $doc = activityProposalPrintDocumentForSnapshotTest($this->org, $this->studentAlpha, null);

    $data = dataForSnapshotProposal($doc);
    expect($data['prepared_by_president'])->toBeNull();
});

function activityProposalPrintDocumentForSnapshotTest(Organization $org, User $submitter, ?string $presidentName): Document
{
    $doc = Document::create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar->value,
        'title' => 'Snapshot Fallback Test',
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);
    $activity = presidentSnapshotApprovedActivity($org);

    ActivityProposal::create([
        'document_id' => $doc->id,
        'calendar_mode' => ProposalCalendarMode::OnCalendar->value,
        'calendar_activity_id' => $activity->id,
        'title' => 'Snapshot Fallback Test',
        'activity_nature' => ActivityNature::CoCurricular->value,
        'activity_type' => ActivityType::Competition->value,
        'target_sdg' => [Sdg::LifeOnLand->value],
        'form_step' => 2,
        'president_name' => $presidentName,
    ]);

    return $doc;
}
