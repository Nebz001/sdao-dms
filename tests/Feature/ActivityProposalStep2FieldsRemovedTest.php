<?php

use App\ActivityProposals\StartProposalDraft;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Group D items 2 and 4 — `narrative` and `source_of_funding` are removed
 * outright, not just left unused. This asserts the removal at the schema
 * level (proof there is no column to write to at all) and confirms the
 * step-2 surfaces genuinely no longer collect or echo either field.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('narrative, source_of_funding, overall_goal, and specific_objectives are not columns on activity_proposals', function () {
    expect(Schema::hasColumn('activity_proposals', 'narrative'))->toBeFalse();
    expect(Schema::hasColumn('activity_proposals', 'source_of_funding'))->toBeFalse();
    // Group E backlog — overall_goal/specific_objectives (themselves a
    // replacement for the original narrative removal) were collapsed back
    // into a single `objectives` field.
    expect(Schema::hasColumn('activity_proposals', 'overall_goal'))->toBeFalse();
    expect(Schema::hasColumn('activity_proposals', 'specific_objectives'))->toBeFalse();

    // The current field exists in their place.
    expect(Schema::hasColumn('activity_proposals', 'objectives'))->toBeTrue();
});

test('a submit request still carrying the old narrative/source_of_funding keys succeeds and does not resurrect them anywhere reachable', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Removed Fields Test Activity',
            'venue' => 'Room 600',
            'activity_date' => '2026-11-20',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
            'activity_nature' => 'co_curricular',
            'activity_type' => 'seminar_workshop',
            'partner_organizations' => ['Partner Org A'],
            'target_sdg' => ['quality_education'],
            'proposed_budget' => '5000.00',
            'budget_source' => 'rso_fund',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $response = $this->actingAs($this->student)->post(route('activity-proposals.submit', $document), [
        'objectives' => 'Overall Goal',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Flow',
        'expense_items' => [['material' => 'Venue', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
        // Stale client / old bookmark / stale JS bundle still sending the
        // removed keys — must be silently ignored, not error, not persisted.
        'narrative' => 'This should go nowhere.',
        'source_of_funding' => 'This should go nowhere either.',
    ]);

    $response->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    // There is no column to have written it to — this is the behavioral
    // proof to go with the schema assertion above.
    $proposal = $document->activityProposal->fresh();
    expect($proposal->getAttributes())->not->toHaveKeys(['narrative', 'source_of_funding']);
});

test('the step-2 continue page never exposes narrative or source_of_funding, and echoes budget_source_label instead', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Removed Fields Continue Test',
            'venue' => 'Room 601',
            'activity_date' => '2026-11-21',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
            'activity_nature' => 'co_curricular',
            'activity_type' => 'seminar_workshop',
            'partner_organizations' => ['Partner Org A'],
            'target_sdg' => ['quality_education'],
            'proposed_budget' => '5000.00',
            'budget_source' => 'rso_savings',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.budget_source_label', 'RSO Savings')
            ->missing('proposal.narrative')
            ->missing('proposal.source_of_funding')
        );
});
