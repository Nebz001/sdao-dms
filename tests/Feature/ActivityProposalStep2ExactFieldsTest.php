<?php

use App\ActivityProposals\StartProposalDraft;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\ProposalCalendarMode;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 7 slice 4b — exact field corrections for the Proposal
 * Narrative (step 2): Criteria/Mechanics, Program Flow, Source of Funding,
 * Expenses. Mirrors the required/nullable treatment already established for
 * objectives/narrative across Submit, Autosave, and Resubmit.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();

    $this->document = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'calendar_mode' => 'off_calendar',
            'title' => 'Step 2 Exact Fields Test Activity',
            'venue' => 'Room 400',
            'activity_date' => '2026-11-05',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'term' => 'first_term',
            'activity_nature' => 'co_curricular',
            'activity_type' => 'seminar_workshop',
            'partner_organizations' => ['Partner Org A'],
            'target_sdg' => 'quality_education',
            'proposed_budget' => '5000.00',
            'budget_source' => 'Org funds',
        ],
    );
});

function step2NarrativeFields(array $overrides = []): array
{
    return array_merge([
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria/Mechanics',
        'program_flow' => 'Program Flow',
        'source_of_funding' => 'Source of Funding',
        // Itemized expenses (client request, post-Part-2) replaced the old
        // free-text `expenses` field as the fourth required narrative field.
        'expense_items' => [
            ['label' => 'Venue rental', 'amount' => '5000.00'],
            ['label' => 'Refreshments', 'amount' => '1500.50'],
        ],
    ], $overrides);
}

// --- Validation: Submit requires all four new fields ---------------------

test('submit validation rejects a step-2 submission missing any of the four new narrative fields', function () {
    $document = $this->document;
    $base = step2NarrativeFields();

    foreach (['criteria_mechanics', 'program_flow', 'source_of_funding', 'expense_items'] as $field) {
        $payload = $base;
        unset($payload[$field]);

        $response = $this->actingAs($this->studentAlpha)
            ->post(route('activity-proposals.submit', $document), $payload);

        $response->assertInvalid([$field]);
        expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
    }
});

test('submit validation rejects an expense item with a negative or non-numeric amount', function () {
    $document = $this->document;

    foreach (['-5', 'not-a-number'] as $badAmount) {
        $response = $this->actingAs($this->studentAlpha)
            ->post(route('activity-proposals.submit', $document), step2NarrativeFields([
                'expense_items' => [['label' => 'Venue rental', 'amount' => $badAmount]],
            ]));

        $response->assertInvalid(['expense_items.0.amount']);
        expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
    }
});

// --- Round-trip: submit step 2 -> stored -> shown (show, review show) ----

test('the four new narrative fields round-trip through step-2 submission and every display surface', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->post(route('activity-proposals.submit', $document), step2NarrativeFields());

    $response->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->criteria_mechanics)->toBe('Criteria/Mechanics');
    expect($proposal->program_flow)->toBe('Program Flow');
    expect($proposal->source_of_funding)->toBe('Source of Funding');
    expect($proposal->expense_items)->toBe([
        ['label' => 'Venue rental', 'amount' => '5000.00'],
        ['label' => 'Refreshments', 'amount' => '1500.50'],
    ]);
    expect($proposal->expenseItemsTotal)->toBe('6,500.50');

    // Student show page.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->where('proposal.criteria_mechanics', 'Criteria/Mechanics')
            ->where('proposal.program_flow', 'Program Flow')
            ->where('proposal.source_of_funding', 'Source of Funding')
            ->where('proposal.expense_items', [
                ['label' => 'Venue rental', 'amount' => '5000.00'],
                ['label' => 'Refreshments', 'amount' => '1500.50'],
            ])
            ->where('proposal.expense_items_total', '6,500.50')
        );

    // Approver (current-step) review show page.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->where('proposal.criteria_mechanics', 'Criteria/Mechanics')
            ->where('proposal.program_flow', 'Program Flow')
            ->where('proposal.source_of_funding', 'Source of Funding')
            ->where('proposal.expense_items_total', '6,500.50')
        );
});

// --- Autosave: nullable, persists while staying Draft ---------------------

test('autosave persists the four new narrative fields and keeps the document Draft', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->patch(route('activity-proposals.draft', $document), [
            'criteria_mechanics' => 'Autosaved Criteria',
            'program_flow' => 'Autosaved Flow',
            'source_of_funding' => 'Autosaved Funding',
            'expense_items' => [['label' => 'Autosaved Item', 'amount' => '250.00']],
        ]);

    $response->assertOk();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Draft);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->criteria_mechanics)->toBe('Autosaved Criteria');
    expect($proposal->program_flow)->toBe('Autosaved Flow');
    expect($proposal->source_of_funding)->toBe('Autosaved Funding');
    expect($proposal->expense_items)->toBe([['label' => 'Autosaved Item', 'amount' => '250.00']]);

    // Step-2 continue view echoes the autosaved values back.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.criteria_mechanics', 'Autosaved Criteria')
            ->where('proposal.program_flow', 'Autosaved Flow')
            ->where('proposal.source_of_funding', 'Autosaved Funding')
            ->where('proposal.expense_items', [['label' => 'Autosaved Item', 'amount' => '250.00']])
        );
});

test('autosave tolerates a half-filled expense row (label typed, amount still empty)', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->patch(route('activity-proposals.draft', $document), [
            'expense_items' => [['label' => 'Venue rental', 'amount' => '']],
        ]);

    $response->assertOk();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Draft);
});

// --- Resubmit: required, round-trips edited values ------------------------

test('resubmitting a Returned proposal round-trips edited values of the four new narrative fields', function () {
    $document = $this->document;

    $this->actingAs($this->studentAlpha)
        ->post(route('activity-proposals.submit', $document), step2NarrativeFields());
    $document->refresh();

    // Off-calendar order (CLAUDE.md invariant #8): SDAO (both) is first.
    $engine = app(ApprovalEngine::class);
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $engine->approve($document, $this->sdaoA);
    $document->refresh();
    $engine->returnForRevision($document, $sdaoB, 'Please revise the mechanics and funding source.');
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);

    $response = $this->actingAs($this->studentAlpha)
        ->put(route('activity-proposals.update', $document), array_merge(
            step2NarrativeFields(),
            [
                'criteria_mechanics' => 'Revised Criteria',
                'program_flow' => 'Revised Flow',
                'source_of_funding' => 'Revised Funding',
                'expense_items' => [['label' => 'Revised Item', 'amount' => '999.99']],
            ],
        ));

    $response->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->criteria_mechanics)->toBe('Revised Criteria');
    expect($proposal->program_flow)->toBe('Revised Flow');
    expect($proposal->source_of_funding)->toBe('Revised Funding');
    expect($proposal->expense_items)->toBe([['label' => 'Revised Item', 'amount' => '999.99']]);
});
