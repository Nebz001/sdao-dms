<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
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
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 item 7 slice 4a — exact field corrections for the Activity
 * Request Form (proposal step 1): Nature of Activity, Type of Activity,
 * Partner Organization(s)/School(s)/RSO, Target SDG, Budget Source (new),
 * plus the Proposed Budget relocation+rename from step 2 (`estimated_budget`)
 * to step 1 (`proposed_budget`). All 5 new fields + budget apply regardless
 * of on/off-calendar mode — a deliberate scope decision, since on-calendar
 * previously required nothing beyond picking an approved activity.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // president, Computing Society
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
});

function approvedCalendarActivityForStep1(Organization $org, string $name = 'Approved Test Event'): CalendarActivity
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

function step1ExactFields(array $overrides = []): array
{
    return array_merge([
        'activity_nature' => 'co_curricular',
        'activity_type' => 'seminar_workshop',
        'partner_organizations' => ['Partner Org A', 'Partner Org B'],
        'target_sdg' => 'quality_education',
        'proposed_budget' => '15000.00',
        'budget_source' => 'Org funds',
    ], $overrides);
}

function offCalendarStep1Payload(array $overrides = []): array
{
    return array_merge([
        'calendar_mode' => 'off_calendar',
        'title' => 'Exact Fields Test Activity',
        'venue' => 'Room 300',
        'activity_date' => '2026-11-01',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'term' => 'first_term',
    ], step1ExactFields(), $overrides);
}

// --- Validation: both modes now require the 5 new fields + budget -------

test('store validation rejects an off-calendar submission missing any of the new step-1 fields', function () {
    $base = offCalendarStep1Payload();

    foreach (['activity_nature', 'activity_type', 'partner_organizations', 'target_sdg', 'proposed_budget', 'budget_source'] as $field) {
        $payload = $base;
        unset($payload[$field]);

        $response = $this->actingAs($this->studentAlpha)->post(route('activity-proposals.store'), $payload);

        $response->assertInvalid([$field]);
    }
});

test('store validation now ALSO requires the new step-1 fields for on-calendar submissions (regression check)', function () {
    $activity = approvedCalendarActivityForStep1($this->computingSociety);

    // On-calendar previously required nothing beyond picking an activity —
    // confirm it now also requires the 5 new fields + budget.
    $response = $this->actingAs($this->studentAlpha)->post(route('activity-proposals.store'), [
        'calendar_mode' => 'on_calendar',
        'calendar_activity_id' => $activity->id,
        // new fields omitted
    ]);

    $response->assertInvalid([
        'activity_nature', 'activity_type', 'partner_organizations', 'target_sdg', 'proposed_budget', 'budget_source',
    ]);
});

test('on-calendar submission succeeds once the new step-1 fields are supplied', function () {
    $activity = approvedCalendarActivityForStep1($this->computingSociety);

    $response = $this->actingAs($this->studentAlpha)->post(route('activity-proposals.store'), array_merge([
        'calendar_mode' => 'on_calendar',
        'calendar_activity_id' => $activity->id,
    ], step1ExactFields()));

    $response->assertRedirect();

    $document = Document::where('form_type', FormType::ActivityProposal->value)
        ->where('organization_id', $this->computingSociety->id)
        ->latest('id')
        ->firstOrFail();

    expect($document->activityProposal->activity_nature->value)->toBe('co_curricular');
    expect($document->activityProposal->target_sdg->value)->toBe('quality_education');
    expect((float) $document->activityProposal->proposed_budget)->toBe(15000.00);
});

// --- Round-trip: submit step 1 -> stored -> shown (step-2 echo, show, review show) ---

test('the 5 new fields and renamed Proposed Budget round-trip through step 1 submission and every display surface', function () {
    $document = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OffCalendar,
        data: offCalendarStep1Payload(),
    );

    $proposal = $document->activityProposal;
    expect($proposal->activity_nature->value)->toBe('co_curricular');
    expect($proposal->activity_type->value)->toBe('seminar_workshop');
    expect($proposal->partner_organizations)->toBe(['Partner Org A', 'Partner Org B']);
    expect($proposal->target_sdg->value)->toBe('quality_education');
    expect($proposal->budget_source)->toBe('Org funds');
    expect((float) $proposal->proposed_budget)->toBe(15000.00);

    // Step 2 continue view — Proposed Budget/Budget Source shown read-only.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.proposed_budget', '15000.00')
            ->where('proposal.budget_source', 'Org funds')
        );

    // Submit step 2 to reach a real (non-Draft) document for show/review-show.
    $this->actingAs($this->studentAlpha)->post(route('activity-proposals.submit', $document), [
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria/Mechanics',
        'program_flow' => 'Program Flow',
        'source_of_funding' => 'Source of Funding',
        'expenses' => 'Expenses',
    ]);
    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);

    // Student show page.
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/show')
            ->where('proposal.activity_nature_label', 'Co-Curricular')
            ->where('proposal.activity_type_label', 'Seminar/Workshop')
            ->where('proposal.partner_organizations', ['Partner Org A', 'Partner Org B'])
            ->where('proposal.target_sdg_label', 'Quality Education')
            ->where('proposal.budget_source', 'Org funds')
            ->where('proposal.proposed_budget', '15000.00')
        );

    // Approver (current-step) review show page.
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.activity-proposals.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/show')
            ->where('proposal.activity_nature_label', 'Co-Curricular')
            ->where('proposal.partner_organizations', ['Partner Org A', 'Partner Org B'])
            ->where('proposal.proposed_budget', '15000.00')
        );
});

// --- Regression: existing on/off-calendar mechanics unaffected ----------

test('off-calendar venue-conflict detection (at step-2 submit) still keys only on venue+date+time, unaffected by the new fields', function () {
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $engine = app(ApprovalEngine::class);
    $submitAction = app(SubmitActivityProposal::class);

    // First off-calendar proposal at a given venue/date/time — submit and
    // fully approve it, so it hard-blocks the slot.
    $firstDoc = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OffCalendar,
        data: offCalendarStep1Payload(['venue' => 'Conflict Hall']),
    );
    $submitAction->execute(actor: $this->studentAlpha, document: $firstDoc, objectives: 'Objectives', narrative: 'Narrative');
    $firstDoc->refresh();

    // Off-calendar order (CLAUDE.md invariant #8): SDAO (both) is FIRST,
    // then adviser -> chair -> dean -> asst dir -> acad dir -> exec dir.
    $engine->approve($firstDoc, $this->sdaoA);
    $firstDoc->refresh();
    $engine->approve($firstDoc, $sdaoB);
    $firstDoc->refresh();
    foreach ([
        'adviser-one@sdao.test', 'chair-cs@sdao.test', 'dean-ccit@sdao.test',
        'asst-director@sdao.test', 'academic-director@sdao.test', 'executive-director@sdao.test',
    ] as $email) {
        $engine->approve($firstDoc, User::where('email', $email)->firstOrFail());
        $firstDoc->refresh();
    }
    expect($firstDoc->status)->toBe(DocumentStatus::Approved); // fully approved -> confirmed hard-block

    // A second off-calendar draft at the SAME venue/date/time but with
    // COMPLETELY DIFFERENT new-field values — the hard-block at step-2
    // submit must still trigger purely on the schedule overlap.
    $secondDoc = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OffCalendar,
        data: offCalendarStep1Payload([
            'venue' => 'Conflict Hall',
            'title' => 'A Different Activity',
            'activity_nature' => 'community_extension',
            'activity_type' => 'outreach',
            'partner_organizations' => ['Totally Different Org'],
            'target_sdg' => 'climate_action',
            'proposed_budget' => '1.00',
            'budget_source' => 'Different source',
        ]),
    );

    expect(fn () => $submitAction->execute(
        actor: $this->studentAlpha,
        document: $secondDoc,
        objectives: 'Objectives',
        narrative: 'Narrative',
    ))->toThrow(ValidationException::class);
});

test('on-calendar linking to an existing Approved CalendarActivity still works with the new required fields present', function () {
    $activity = approvedCalendarActivityForStep1($this->computingSociety, 'Regression Check Event');

    $document = $this->startDraft->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: array_merge(['calendar_activity_id' => $activity->id], step1ExactFields()),
    );

    expect($document->activityProposal->calendar_activity_id)->toBe($activity->id);
    expect($document->activityProposal->title)->toBe('Regression Check Event');
    expect($document->title)->toContain('Regression Check Event');
});
