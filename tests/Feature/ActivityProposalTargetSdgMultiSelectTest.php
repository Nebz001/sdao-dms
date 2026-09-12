<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Enums\Sdg;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Printing\ActivityProposalForm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Group C item 1 — target_sdg multi-select. Same shape and cast pattern as
 * the Group B calendar fix (CalendarActivitySdgMultiSelectTest); this suite
 * covers the parts that genuinely differ: no array-index wildcards (one row
 * per document, not per activity), and the print-form/show-page wiring.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function targetSdgApprovedActivity(Organization $org): CalendarActivity
{
    $doc = Document::create([
        'form_type' => 'activity_calendar',
        'variant' => null,
        'title' => 'Approved Calendar',
        'status' => 'approved',
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => null,
    ]);
    $cal = ActivityCalendar::create(['document_id' => $doc->id, 'academic_year' => '2026-2027', 'term' => 'first_term']);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => 'Target SDG Test Event',
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

test('store validation rejects an empty target_sdg selection', function () {
    $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
        offCalendarStep1Payload(['target_sdg' => []]),
        ['attachments' => proposalStepOneAttachmentFiles()],
    ));

    $response->assertInvalid(['target_sdg']);
});

test('store validation rejects an invalid target_sdg item', function () {
    $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
        offCalendarStep1Payload(['target_sdg' => ['not_a_real_sdg']]),
        ['attachments' => proposalStepOneAttachmentFiles()],
    ));

    $response->assertInvalid(['target_sdg.0']);
});

test('a single selected SDG round-trips as a one-element collection', function () {
    $activity = targetSdgApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields([
            'target_sdg' => [Sdg::NoPoverty->value],
        ])),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    expect($document->activityProposal->target_sdg)->toHaveCount(1);
    expect($document->activityProposal->target_sdg->first())->toBe(Sdg::NoPoverty);
});

test('multiple selected SDGs round-trip through submission and both show pages', function () {
    // Off-calendar: SDAO is step 1 (invariant #8), so SDAO can view the
    // review-show page immediately after step-2 submit — matches the
    // established precedent in ActivityProposalStep1ExactFieldsTest's own
    // "round-trip through submission and both show pages" test.
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: offCalendarStep1Payload([
            'target_sdg' => [Sdg::QualityEducation->value, Sdg::ClimateAction->value],
        ]),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $proposal = $document->activityProposal;
    expect($proposal->target_sdg)->toHaveCount(2);
    expect($proposal->target_sdg->map(fn (Sdg $s) => $s->value)->all())
        ->toBe([Sdg::QualityEducation->value, Sdg::ClimateAction->value]);

    $this->submitProposal->execute(
        actor: $this->student,
        document: $document,
        overallGoal: 'Overall Goal',
        specificObjectives: 'Specific Objectives',
    );
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $expectedLabels = [Sdg::QualityEducation->label(), Sdg::ClimateAction->label()];

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->where('proposal.target_sdg_labels', $expectedLabels)
        );

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->where('proposal.target_sdg_labels', $expectedLabels)
        );
});

test('multiple SDGs print as one "SDG N — Label" line each, semicolon-separated', function () {
    $activity = targetSdgApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields([
            'target_sdg' => [Sdg::ClimateAction->value, Sdg::QualityEducation->value],
        ])),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $form = app(ActivityProposalForm::class);
    $document->load($form->eagerLoads());
    $data = $form->data($document);

    expect($data['target_sdg'])->toBe('SDG 13 — Climate Action; SDG 4 — Quality Education');
});
