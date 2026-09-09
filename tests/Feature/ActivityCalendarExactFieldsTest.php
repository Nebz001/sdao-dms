<?php

use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\Sdg;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Validation\ValidationException;

/**
 * Phase 2 item 7 slice 1 — exact field corrections for the Activity Calendar:
 * SDG, Participant/Program Assigned, Budget (new), plus RSO Name / Date
 * Received (already-derived values, now actually displayed with those exact
 * labels). See the approved plan for the nullable-DB/required-validation
 * trade-off this suite exercises.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->action = app(SubmitActivityCalendar::class);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
});

function exactFieldsActivity(array $overrides = []): array
{
    return array_merge([
        'name' => 'JS Night',
        'venue' => 'Gymnasium',
        'activity_date' => '2026-09-15',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'sdg' => Sdg::QualityEducation->value,
        'participant_program_assigned' => 'BSCS — All Year Levels',
        'budget' => '15000.00',
    ], $overrides);
}

// --- Validation ---------------------------------------------------------

test('store validation rejects a submission missing sdg, participant_program_assigned, or budget', function () {
    $base = exactFieldsActivity();

    foreach (['sdg', 'participant_program_assigned', 'budget'] as $field) {
        $activity = $base;
        unset($activity[$field]);

        $response = $this->actingAs($this->studentAlpha)
            ->post(route('activity-calendars.store'), ['activities' => [$activity]]);

        $response->assertInvalid(["activities.0.{$field}"]);
    }
});

test('store validation rejects an invalid sdg value', function () {
    $response = $this->actingAs($this->studentAlpha)
        ->post(route('activity-calendars.store'), [
            'activities' => [exactFieldsActivity(['sdg' => 'not_a_real_sdg'])],
        ]);

    $response->assertInvalid(['activities.0.sdg']);
});

// --- Round-trip: submit -> stored -> shown (student + approver) --------

test('SDG, Participant/Program Assigned, and Budget round-trip through submission and both show pages', function () {
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: [exactFieldsActivity()],
    );
    $document = $result['document'];

    $activity = $document->activityCalendar->activities->first();
    expect($activity->sdg)->toBe(Sdg::QualityEducation);
    expect($activity->participant_program_assigned)->toBe('BSCS — All Year Levels');
    expect((float) $activity->budget)->toBe(15000.00);

    // Student show page
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/show')
            ->where('calendar.activities.0.sdg_label', Sdg::QualityEducation->label())
            ->where('calendar.activities.0.participant_program_assigned', 'BSCS — All Year Levels')
            ->where('document.rso_name', $this->org->name)
            ->has('document.date_received')
        );

    // Approver (SDAO) show page — must see the same fields to make a decision
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->get(route('review.activity-calendars.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-calendars/show')
            ->where('calendar.activities.0.sdg_label', Sdg::QualityEducation->label())
            ->where('calendar.activities.0.participant_program_assigned', 'BSCS — All Year Levels')
            ->where('document.rso_name', $this->org->name)
            ->has('document.date_received')
        );
});

test('Date Received renders on the index list from the document\'s real created_at', function () {
    $result = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: [exactFieldsActivity()],
    );

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/index')
            ->has('calendars.0.created_at')
        );

    expect($result['document']->created_at)->not->toBeNull();
});

// --- One activity calendar per term — create() eligibility prop --------

test('create() reports eligible when the org has no calendar for the current term', function () {
    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-calendars/create')
            ->where('eligibility.status', 'eligible')
            ->where('eligibility.message', null)
        );
});

test('create() reports already_filed with a pending-review message while the existing calendar is Draft/InReview', function () {
    $result = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: [exactFieldsActivity()]);

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('eligibility.status', 'already_filed')
            ->where('eligibility.message', fn ($m) => str_contains($m, 'pending SDAO review'))
            ->where('eligibility.existingDocument.id', $result['document']->id)
            ->where('eligibility.existingDocument.status', 'in_review')
            ->where('eligibility.existingDocument.href', route('activity-calendars.show', $result['document']))
        );
});

test('create() points at editing the existing calendar when it was Returned', function () {
    $result = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: [exactFieldsActivity()]);
    $this->engine->returnForRevision($result['document'], $this->sdaoA, 'Needs more detail.');
    $result['document']->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('eligibility.status', 'already_filed')
            ->where('eligibility.message', fn ($m) => str_contains($m, 'returned for revision'))
            ->where('eligibility.existingDocument.status', 'returned')
            ->where('eligibility.existingDocument.href', route('activity-calendars.edit', $result['document']))
        );
});

test('create() reports the already-approved message once the existing calendar is Approved', function () {
    $result = $this->action->execute(actor: $this->studentAlpha, organization: $this->org, activities: [exactFieldsActivity()]);
    $this->engine->approve($result['document'], $this->sdaoA);
    $this->engine->approve($result['document'], $this->sdaoB);
    $result['document']->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('activity-calendars.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('eligibility.status', 'already_filed')
            ->where('eligibility.message', fn ($m) => str_contains($m, 'already been approved'))
            ->where('eligibility.existingDocument.status', 'approved')
            ->where('eligibility.existingDocument.href', route('activity-calendars.show', $result['document']))
        );
});

// --- Regression: new fields don't affect venue-conflict detection -------

test('venue-conflict detection is unaffected by differing SDG/participant/budget values (CLAUDE.md invariant #6)', function () {
    // First calendar approved at a given venue/date/time.
    $first = $this->action->execute(
        actor: $this->studentAlpha,
        organization: $this->org,
        activities: [exactFieldsActivity([
            'sdg' => Sdg::ClimateAction->value,
            'budget' => '1000.00',
        ])],
    );
    $this->engine->approve($first['document'], $this->sdaoA);
    $first['document']->refresh();
    $this->engine->approve($first['document'], $this->sdaoB);

    // A second org submits an overlapping activity with DIFFERENT sdg/budget —
    // conflict must still be detected purely on venue+date+time.
    $itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();

    expect(fn () => $this->action->execute(
        actor: $studentBeta,
        organization: $itGuild,
        activities: [exactFieldsActivity([
            'sdg' => Sdg::GenderEquality->value,
            'participant_program_assigned' => 'Completely different audience',
            'budget' => '999999.00',
        ])],
    ))->toThrow(ValidationException::class);
});
