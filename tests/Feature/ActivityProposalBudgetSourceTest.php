<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Enums\BudgetSource;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
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
 * Group C item 2 — Budget Source becomes a closed dropdown (RSO Fund / RSO
 * Savings / External), was free text. Same Rule::enum() + Select-swap
 * pattern already used for ActivityNature/ActivityType.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function budgetSourceApprovedActivity(Organization $org): CalendarActivity
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
        'name' => 'Budget Source Test Event',
        'venue' => 'Auditorium',
        'activity_date' => '2026-10-20',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
}

test('store validation rejects a free-text budget_source value', function () {
    $response = $this->actingAs($this->student)->post(route('activity-proposals.store'), array_merge(
        offCalendarStep1Payload(['budget_source' => 'Org funds, sponsorship']),
        ['attachments' => proposalStepOneAttachmentFiles()],
    ));

    $response->assertInvalid(['budget_source']);
});

test('store accepts each of the three real BudgetSource options', function (BudgetSource $case) {
    $activity = budgetSourceApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields([
            'budget_source' => $case->value,
        ])),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    expect($document->activityProposal->budget_source)->toBe($case);
})->with(BudgetSource::cases());

test('the selected budget source round-trips through submission and both show pages as its label', function () {
    // Off-calendar: SDAO is step 1 (invariant #8), so SDAO can view the
    // review-show page immediately after step-2 submit.
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: offCalendarStep1Payload([
            'budget_source' => BudgetSource::RsoSavings->value,
        ]),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $this->submitProposal->execute(
        actor: $this->student,
        document: $document,
        overallGoal: 'Overall Goal',
        specificObjectives: 'Specific Objectives',
    );
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->where('proposal.budget_source_label', 'RSO Savings')
        );

    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->where('proposal.budget_source_label', 'RSO Savings')
        );
});

test('budget source prints its label on the request form', function () {
    $activity = budgetSourceApprovedActivity($this->org);

    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields([
            'budget_source' => BudgetSource::External->value,
        ])),
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $form = app(ActivityProposalForm::class);
    $document->load($form->eagerLoads());
    $data = $form->data($document);

    expect($data['budget_source'])->toBe('External');
});
