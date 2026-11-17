<?php

use App\ActivityProposals\ProposalReference;
use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Approval\ReturnedEditLock;
use App\Approval\SectionFields;
use App\Approval\SectionFlags;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalCalendarMode;
use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * Specific Objectives (activity_proposals.specific_objectives): an optional, one
 * objective per line textarea under Objectives, stored as plain text with
 * newlines. It rides the existing "objectives" flag section, so the revision
 * history and Fix and Resend lock cover it with no second flag system.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
});

function specificObjectivesDraft(): Document
{
    return app(StartProposalDraft::class)->execute(
        actor: User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail(),
        organization: Organization::where('name', 'Computing Society')->firstOrFail(),
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'calendar_mode' => 'off_calendar',
            'title' => 'Specific Objectives Activity',
            'venue' => 'Room 400',
            'activity_date' => '2027-11-05',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'activity_nature' => 'co_curricular',
            'activity_type' => 'seminar_workshop',
            'partner_organizations' => [['organization_id' => null, 'name' => 'Partner Org A']],
            'target_sdg' => ['quality_education'],
            'proposed_budget' => '5000.00',
            'budget_source' => 'rso_fund',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
}

/** @param  array<string, mixed>  $overrides */
function specificObjectivesNarrative(array $overrides = []): array
{
    return [
        'objectives' => 'Overall goal.',
        'activity_description' => 'Describe it.',
        'criteria_mechanics' => 'Criteria.',
        'program_flow' => 'Flow.',
        'expense_items' => [['material' => 'Tarp', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Ana'],
        ...$overrides,
    ];
}

/**
 * A Returned proposal (flagged by the adviser) that already has Specific Objectives.
 *
 * @param  array<int, string>  $flagged
 */
function specificObjectivesReturned(array $flagged, ?string $lines = "Train 30 members\nHold a mock defense"): Document
{
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();

    ['document' => $document] = app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: specificObjectivesDraft(),
        objectives: 'Overall goal.',
        activityDescription: 'Describe it.',
        criteriaMechanics: 'Criteria.',
        programFlow: 'Flow.',
        expenseItems: [['material' => 'Tarp', 'quantity' => '1', 'unit_price' => '100.00']],
        responsiblePersons: ['Ana'],
        specificObjectives: $lines,
    );

    app(ApprovalEngine::class)->returnForRevision($document->refresh(), $adviser, 'Please revise.', $flagged);

    return $document->refresh();
}

// ── Validation ──────────────────────────────────────────────────────────

test('submit accepts a proposal with no specific objectives and stores null', function () {
    $document = specificObjectivesDraft();

    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative())
        ->assertSessionHasNoErrors();

    expect($document->fresh()->activityProposal->specific_objectives)->toBeNull();
});

test('a whitespace-only value is treated as empty, not as an error', function () {
    $document = specificObjectivesDraft();

    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative(['specific_objectives' => "   \n  \n "]))
        ->assertSessionHasNoErrors();

    expect($document->fresh()->activityProposal->specific_objectives)->toBeNull();
});

test('submit, draft autosave and resubmit all cap the length at 2000 characters', function () {
    $tooLong = str_repeat('a', 2001);

    $document = specificObjectivesDraft();
    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative(['specific_objectives' => $tooLong]))
        ->assertInvalid(['specific_objectives']);
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft);

    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $document), ['specific_objectives' => $tooLong])
        ->assertSessionHasErrors('specific_objectives');
    expect($document->fresh()->activityProposal->specific_objectives)->toBeNull();

    $returned = specificObjectivesReturned(['objectives']);
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $returned), ['objectives' => 'A sharper goal.', 'specific_objectives' => $tooLong])
        ->assertInvalid(['specific_objectives']);
});

test('the validation message names the field Specific Objectives', function () {
    $document = specificObjectivesDraft();

    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative(['specific_objectives' => str_repeat('a', 2001)]))
        ->assertInvalid(['specific_objectives' => 'The Specific Objectives field must not be greater than 2000 characters.']);
});

test('a browser form post with CRLF line breaks is stored with plain newlines and counts each break once', function () {
    $document = specificObjectivesDraft();
    // 15 lines of 132 characters: 1,994 as the page counts it, 2,008 if each CRLF counted twice.
    $lines = collect(range(1, 15))->map(fn ($n) => str_repeat('a', 132))->implode("\r\n");
    expect(mb_strlen($lines))->toBeGreaterThan(2000);
    expect(mb_strlen(str_replace("\r\n", "\n", $lines)))->toBeLessThanOrEqual(2000);

    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative(['specific_objectives' => $lines]))
        ->assertSessionHasNoErrors();

    $stored = $document->fresh()->activityProposal->specific_objectives;
    expect($stored)->not->toContain("\r");
    expect(substr_count($stored, "\n"))->toBe(14);
});

test('the draft autosave and the resubmit store the same plain newlines as a submit', function () {
    $draft = specificObjectivesDraft();
    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $draft), ['specific_objectives' => "One\r\nTwo"])
        ->assertOk();
    expect($draft->fresh()->activityProposal->specific_objectives)->toBe("One\nTwo");

    $returned = specificObjectivesReturned(['objectives']);
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $returned), ['objectives' => 'A sharper goal.', 'specific_objectives' => "Train 40\r\nHold a mock defense"])
        ->assertSessionHasNoErrors();
    expect($returned->refresh()->activityProposal->specific_objectives)->toBe("Train 40\nHold a mock defense");
});

test('text that only differs by CRLF versus LF does not count as a change to a flagged section', function () {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'Overall goal.', 'specific_objectives' => "Train 30 members\r\nHold a mock defense"])
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Objectives.']);
});

// ── Save ────────────────────────────────────────────────────────────────

test('submit stores specific objectives as plain text with newlines, untouched in between', function () {
    $document = specificObjectivesDraft();
    $lines = "Train 30 members\n\nHold a mock defense\n  Publish the results";

    $this->actingAs($this->student)
        ->post(route('activity-proposals.submit', $document), specificObjectivesNarrative(['specific_objectives' => $lines]))
        ->assertSessionHasNoErrors();

    $proposal = $document->fresh()->activityProposal;
    expect($document->fresh()->status)->toBe(DocumentStatus::InReview);
    expect($proposal->specific_objectives)->toBe($lines);
    // The single Objectives field is a separate column and is untouched.
    expect($proposal->objectives)->toBe('Overall goal.');
    // Plain text in the database, never JSON.
    expect(json_decode((string) $proposal->specific_objectives, true))->toBeNull();
});

test('the draft autosave keeps, replaces and clears specific objectives', function () {
    $document = specificObjectivesDraft();

    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $document), ['specific_objectives' => "One\nTwo"])
        ->assertOk();
    expect($document->fresh()->activityProposal->specific_objectives)->toBe("One\nTwo");

    // An autosave that does not mention the field leaves it alone.
    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $document), ['objectives' => 'Only the goal'])
        ->assertOk();
    expect($document->fresh()->activityProposal->specific_objectives)->toBe("One\nTwo");

    // Emptying the textarea sends null and clears it.
    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $document), ['specific_objectives' => null])
        ->assertOk();
    expect($document->fresh()->activityProposal->specific_objectives)->toBeNull();
});

test('the step-two, edit, student and reviewer pages all receive the stored text', function () {
    $draft = specificObjectivesDraft();
    $this->actingAs($this->student)
        ->patchJson(route('activity-proposals.draft', $draft), ['specific_objectives' => "One\nTwo"]);

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-proposals.continue', $draft))
        ->assertInertia(fn ($page) => $page->where('proposal.specific_objectives', "One\nTwo")->etc());

    $returned = specificObjectivesReturned(['objectives']);
    $stored = "Train 30 members\nHold a mock defense";

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-proposals.edit', $returned))
        ->assertInertia(fn ($page) => $page->where('proposal.specific_objectives', $stored)->etc());

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-proposals.show', $returned))
        ->assertInertia(fn ($page) => $page->where('proposal.specific_objectives', $stored)->etc());

    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $returned))
        ->assertInertia(fn ($page) => $page->where('proposal.specific_objectives', $stored)->etc());
});

// ── Resubmit ────────────────────────────────────────────────────────────

test('resubmit saves a changed specific objectives value and records it in the history', function () {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [
            'objectives' => 'A sharper goal.',
            'specific_objectives' => "Train 40 members\nHold a mock defense\nPublish the results",
        ])
        ->assertSessionHasNoErrors();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    expect($document->activityProposal->specific_objectives)->toBe("Train 40 members\nHold a mock defense\nPublish the results");

    $changes = $document->transitions()->where('action', TransitionAction::Resubmitted->value)->latest('id')->firstOrFail()->field_changes;
    $fields = collect($changes['objectives']['fields'])->keyBy('key');
    expect($fields['specific_objectives']['label'])->toBe('Specific Objectives');
    expect($fields['specific_objectives']['old'])->toBe("Train 30 members\nHold a mock defense");
    expect($fields['specific_objectives']['new'])->toBe("Train 40 members\nHold a mock defense\nPublish the results");
    expect($fields['specific_objectives']['changed'])->toBeTrue();
});

test('resubmit keeps the stored specific objectives when the page does not post them', function () {
    $document = specificObjectivesReturned(['objectives']);

    // The Fix and Resend page only posts what was flagged; here, Objectives alone.
    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'A sharper goal.'])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->activityProposal->specific_objectives)->toBe("Train 30 members\nHold a mock defense");
});

test('resubmit clears specific objectives when the textarea is emptied', function () {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'A sharper goal.', 'specific_objectives' => ''])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->activityProposal->specific_objectives)->toBeNull();
});

test('a proposal returned without specific objectives can gain them on resubmit', function () {
    $document = specificObjectivesReturned(['objectives'], lines: null);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'Same goal, edited.', 'specific_objectives' => "First\nSecond"])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->activityProposal->specific_objectives)->toBe("First\nSecond");
});

// ── Section flagging ────────────────────────────────────────────────────

test('specific objectives belong to the existing Objectives flag section, with no new flag key', function () {
    $keys = collect(SectionFlags::for(FormType::ActivityProposal))->pluck('key')->all();
    $fields = collect(SectionFields::definitionsFor(FormType::ActivityProposal, 'objectives'))->pluck('key')->all();

    expect($fields)->toBe(['objectives', 'specific_objectives']);
    expect($keys)->toContain('objectives')->not->toContain('specific_objectives')->not->toContain('specific_objectives');
});

test('flagging Objectives makes the whole section, Specific Objectives included, editable and the rest locked', function () {
    $document = specificObjectivesReturned(['objectives']);
    $lock = ReturnedEditLock::for($document);

    expect($lock)->not->toBeNull();
    expect(collect(ReturnedEditLock::fieldMap($document)['objectives'])->pluck('key')->all())->toBe(['objectives', 'specific_objectives']);
    expect($lock->isFixable('objectives'))->toBeTrue()->and($lock->isFixable('activity_description'))->toBeFalse();
});

test('changing only specific objectives satisfies a flagged Objectives section', function () {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'Overall goal.', 'specific_objectives' => "Brand new\nlist"])
        ->assertSessionHasNoErrors();

    expect($document->refresh()->status)->toBe(DocumentStatus::InReview);
});

test('a flagged Objectives section still has to change before it can be resent', function () {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), ['objectives' => 'Overall goal.', 'specific_objectives' => "Train 30 members\nHold a mock defense"])
        ->assertSessionHasErrors(['flagged' => 'Still needs your changes: Objectives.']);

    expect($document->refresh()->status)->toBe(DocumentStatus::Returned);
});

test('specific objectives cannot change when only another section was flagged', function () {
    $document = specificObjectivesReturned(['activity_description']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [
            // The whole flagged section, as the page posts it.
            'activity_description' => 'Rewritten description.',
            'criteria_mechanics' => 'Criteria.',
            'program_flow' => 'Flow.',
            'specific_objectives' => 'Sneaked in',
        ])
        ->assertSessionHasErrors(['locked' => 'These were not flagged, so they can’t be changed: Specific Objectives.']);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);
    expect($document->activityProposal->specific_objectives)->toBe("Train 30 members\nHold a mock defense");
    // The refusal rolled back the flagged field too.
    expect($document->activityProposal->activity_description)->toBe('Describe it.');
});

test('an approver return that flags Objectives stores the section key and the comment per section', function () {
    ['document' => $document] = app(SubmitActivityProposal::class)->execute(
        actor: $this->student,
        document: specificObjectivesDraft(),
        objectives: 'Overall goal.',
        specificObjectives: "One\nTwo",
    );

    app(ApprovalEngine::class)->returnForRevision($document, $this->adviser, 'Tighten the objectives.', ['objectives'], ['objectives' => 'Make each one measurable.']);
    $document->refresh();

    expect(SectionFlags::currentlyFlagged($document))->toBe(['objectives']);
    expect(SectionFlags::currentSectionComments($document))->toBe(['objectives' => 'Make each one measurable.']);
});

// The one shared 'objectives' flag key is by design: Objectives and Specific
// Objectives lock and unlock together.

test('with Objectives flagged, either field alone, or both, unlock the section', function (array $payload) {
    $document = specificObjectivesReturned(['objectives']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), $payload)
        ->assertSessionHasNoErrors();

    expect($document->refresh()->status)->toBe(DocumentStatus::InReview);
})->with([
    'general Objectives only' => [['objectives' => 'A sharper goal.']],
    'Specific Objectives only' => [['objectives' => 'Overall goal.', 'specific_objectives' => "One\nTwo"]],
    'both' => [['objectives' => 'A sharper goal.', 'specific_objectives' => "One\nTwo"]],
]);

test('with Objectives not flagged, both fields are locked, and a refusal names the one that moved', function (array $payload, string $named) {
    $document = specificObjectivesReturned(['activity_description']);

    $this->actingAs($this->student)
        ->put(route('activity-proposals.update', $document), [
            // The whole flagged section, as the page posts it.
            'activity_description' => 'Rewritten description.',
            'criteria_mechanics' => 'Criteria.',
            'program_flow' => 'Flow.',
            ...$payload,
        ])
        ->assertSessionHasErrors(['locked' => "These were not flagged, so they can’t be changed: {$named}."]);

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::Returned);
    expect($document->activityProposal->objectives)->toBe('Overall goal.');
    expect($document->activityProposal->specific_objectives)->toBe("Train 30 members\nHold a mock defense");
})->with([
    'general Objectives' => [['objectives' => 'Sneaked in'], 'Objectives'],
    'Specific Objectives' => [['specific_objectives' => 'Sneaked in'], 'Specific Objectives'],
]);

test('the edit page lists Objectives as one fixable item when flagged, and as none when not', function () {
    $flagged = specificObjectivesReturned(['objectives']);
    $lock = ReturnedEditLock::for($flagged);
    expect($lock->fixable)->toBe(['objectives']);

    $this->actingAs($this->student)->withoutVite()
        ->get(route('activity-proposals.edit', $flagged))
        ->assertInertia(fn ($page) => $page->where('fix.items.0.key', 'objectives')->has('fix.items', 1)->etc());
});

// ── Mobile API ──────────────────────────────────────────────────────────

test('the mobile api exposes specific objectives as a clean ordered list', function () {
    $document = specificObjectivesReturned(['objectives'], "1. Train 30 members\n\n- Hold a mock defense");
    $reference = ProposalReference::format($document);
    $token = $this->adviser->createToken('Phone')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson("/api/documents/{$reference}");

    $response->assertOk();
    expect($response->json('activity_proposal.specific_objectives'))->toBe(['Train 30 members', 'Hold a mock defense']);
});
