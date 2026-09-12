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
 * Narrative (step 2): Criteria/Mechanics, Program Flow, Expenses.
 *
 * Group D — reshaped for the second remediation pass:
 *   - Objectives split into Overall Goal + Specific Objectives (item 1).
 *   - `narrative` removed outright — a leftover scaffold field never in the
 *     client's real form (item 2).
 *   - Expenses rows are {material, quantity, unit_price} with a computed row
 *     total and grand total, not {label, amount} (item 3).
 *   - Source of Funding removed — step 2 now echoes step 1's budget_source
 *     read-only instead of asking again (item 4).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(); // president, Computing Society

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
            'target_sdg' => ['quality_education'],
            'proposed_budget' => '5000.00',
            'budget_source' => 'rso_fund',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
});

function step2NarrativeFields(array $overrides = []): array
{
    return array_merge([
        'overall_goal' => 'Overall Goal',
        'specific_objectives' => 'Specific Objectives',
        'criteria_mechanics' => 'Criteria/Mechanics',
        'program_flow' => 'Program Flow',
        'expense_items' => [
            ['material' => 'Venue rental', 'quantity' => '1', 'unit_price' => '5000.00'],
            ['material' => 'Refreshments', 'quantity' => '2', 'unit_price' => '750.25'],
        ],
    ], $overrides);
}

// --- Validation: Submit requires every step-2 field -----------------------

test('submit validation rejects a step-2 submission missing any required narrative field', function () {
    $document = $this->document;
    $base = step2NarrativeFields();

    foreach (['overall_goal', 'specific_objectives', 'criteria_mechanics', 'program_flow', 'expense_items'] as $field) {
        $payload = $base;
        unset($payload[$field]);

        $response = $this->actingAs($this->studentAlpha)
            ->post(route('activity-proposals.submit', $document), $payload);

        $response->assertInvalid([$field]);
        expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
    }
});

test('submit validation rejects an expense item missing material, quantity, or unit_price', function () {
    $document = $this->document;

    foreach (['material', 'quantity', 'unit_price'] as $missing) {
        $row = ['material' => 'Venue rental', 'quantity' => '2', 'unit_price' => '500.00'];
        unset($row[$missing]);

        $response = $this->actingAs($this->studentAlpha)
            ->post(route('activity-proposals.submit', $document), step2NarrativeFields([
                'expense_items' => [$row],
            ]));

        $response->assertInvalid(["expense_items.0.{$missing}"]);
        expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
    }
});

test('submit validation rejects an expense item with a negative or non-numeric quantity or unit_price', function () {
    $document = $this->document;

    foreach (['quantity', 'unit_price'] as $field) {
        foreach (['-5', 'not-a-number'] as $badValue) {
            $row = ['material' => 'Venue rental', 'quantity' => '2', 'unit_price' => '500.00', $field => $badValue];

            $response = $this->actingAs($this->studentAlpha)
                ->post(route('activity-proposals.submit', $document), step2NarrativeFields([
                    'expense_items' => [$row],
                ]));

            $response->assertInvalid(["expense_items.0.{$field}"]);
            expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
        }
    }
});

// --- Round-trip: submit step 2 -> stored -> shown (show, review show) ----

test('overall_goal, specific_objectives, criteria_mechanics, and program_flow round-trip through step-2 submission and every display surface', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->post(route('activity-proposals.submit', $document), step2NarrativeFields());

    $response->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->overall_goal)->toBe('Overall Goal');
    expect($proposal->specific_objectives)->toBe('Specific Objectives');
    expect($proposal->criteria_mechanics)->toBe('Criteria/Mechanics');
    expect($proposal->program_flow)->toBe('Program Flow');
    expect($proposal->expense_items)->toBe([
        ['material' => 'Venue rental', 'quantity' => '1', 'unit_price' => '5000.00'],
        ['material' => 'Refreshments', 'quantity' => '2', 'unit_price' => '750.25'],
    ]);
    // 1×5000.00 + 2×750.25 = 6,500.50
    expect($proposal->expenseItemsTotal)->toBe('6,500.50');

    // Student show page.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->where('proposal.overall_goal', 'Overall Goal')
            ->where('proposal.specific_objectives', 'Specific Objectives')
            ->where('proposal.criteria_mechanics', 'Criteria/Mechanics')
            ->where('proposal.program_flow', 'Program Flow')
            ->where('proposal.expense_items', [
                ['material' => 'Venue rental', 'quantity' => '1', 'unit_price' => '5000.00'],
                ['material' => 'Refreshments', 'quantity' => '2', 'unit_price' => '750.25'],
            ])
            ->where('proposal.expense_items_total', '6,500.50')
        );

    // Approver (current-step) review show page — adviser is step 1
    // regardless of calendar mode (invariant #8).
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->actingAs($adviser)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->where('proposal.overall_goal', 'Overall Goal')
            ->where('proposal.specific_objectives', 'Specific Objectives')
            ->where('proposal.criteria_mechanics', 'Criteria/Mechanics')
            ->where('proposal.program_flow', 'Program Flow')
            ->where('proposal.expense_items_total', '6,500.50')
        );
});

test('a decimal quantity computes the correct row total and grand total', function () {
    $document = $this->document;

    $this->actingAs($this->studentAlpha)
        ->post(route('activity-proposals.submit', $document), step2NarrativeFields([
            'expense_items' => [
                ['material' => 'Catering (kg)', 'quantity' => '2.5', 'unit_price' => '400.00'],
            ],
        ]));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $proposal = $document->activityProposal->fresh();
    // 2.5 × 400.00 = 1,000.00
    expect($proposal->expenseItemsTotal)->toBe('1,000.00');
});

// --- Autosave: nullable, persists while staying Draft ---------------------

test('autosave persists overall_goal, specific_objectives, criteria_mechanics, program_flow, and expense_items and keeps the document Draft', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->patch(route('activity-proposals.draft', $document), [
            'overall_goal' => 'Autosaved Overall Goal',
            'specific_objectives' => 'Autosaved Specific Objectives',
            'criteria_mechanics' => 'Autosaved Criteria',
            'program_flow' => 'Autosaved Flow',
            'expense_items' => [['material' => 'Autosaved Item', 'quantity' => '1', 'unit_price' => '250.00']],
        ]);

    $response->assertOk();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Draft);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->overall_goal)->toBe('Autosaved Overall Goal');
    expect($proposal->specific_objectives)->toBe('Autosaved Specific Objectives');
    expect($proposal->criteria_mechanics)->toBe('Autosaved Criteria');
    expect($proposal->program_flow)->toBe('Autosaved Flow');
    expect($proposal->expense_items)->toBe([['material' => 'Autosaved Item', 'quantity' => '1', 'unit_price' => '250.00']]);

    // Step-2 continue view echoes the autosaved values back.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.overall_goal', 'Autosaved Overall Goal')
            ->where('proposal.specific_objectives', 'Autosaved Specific Objectives')
            ->where('proposal.criteria_mechanics', 'Autosaved Criteria')
            ->where('proposal.program_flow', 'Autosaved Flow')
            ->where('proposal.expense_items', [['material' => 'Autosaved Item', 'quantity' => '1', 'unit_price' => '250.00']])
        );
});

test('autosave tolerates a half-filled expense row (material typed, quantity/unit_price still empty)', function () {
    $document = $this->document;

    $response = $this->actingAs($this->studentAlpha)
        ->patch(route('activity-proposals.draft', $document), [
            'expense_items' => [['material' => 'Venue rental', 'quantity' => '', 'unit_price' => '']],
        ]);

    $response->assertOk();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Draft);
});

// --- Resubmit: required, round-trips edited values ------------------------

test('resubmitting a Returned proposal round-trips edited values of every step-2 field', function () {
    $document = $this->document;

    $this->actingAs($this->studentAlpha)
        ->post(route('activity-proposals.submit', $document), step2NarrativeFields());
    $document->refresh();

    // Adviser is step 1 regardless of calendar mode (invariant #8).
    $engine = app(ApprovalEngine::class);
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $engine->returnForRevision($document, $adviser, 'Please revise the mechanics and expenses.');
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);

    $response = $this->actingAs($this->studentAlpha)
        ->put(route('activity-proposals.update', $document), array_merge(
            step2NarrativeFields(),
            [
                'overall_goal' => 'Revised Overall Goal',
                'specific_objectives' => 'Revised Specific Objectives',
                'criteria_mechanics' => 'Revised Criteria',
                'program_flow' => 'Revised Flow',
                'expense_items' => [['material' => 'Revised Item', 'quantity' => '3', 'unit_price' => '333.33']],
            ],
        ));

    $response->assertRedirect();
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    $proposal = $document->activityProposal->fresh();
    expect($proposal->overall_goal)->toBe('Revised Overall Goal');
    expect($proposal->specific_objectives)->toBe('Revised Specific Objectives');
    expect($proposal->criteria_mechanics)->toBe('Revised Criteria');
    expect($proposal->program_flow)->toBe('Revised Flow');
    expect($proposal->expense_items)->toBe([['material' => 'Revised Item', 'quantity' => '3', 'unit_price' => '333.33']]);
});
