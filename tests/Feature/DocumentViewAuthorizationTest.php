<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Calendar\SubmitActivityCalendar;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use App\Reports\SubmitAfterActivityReport;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Covers the IDOR gap found in the full gap audit: student-facing show()
 * methods previously had no authorization at all. DocumentPolicy::view()
 * now allows either (a) an affiliated officer of the document's own
 * organization, or (b) an approver whose current step in this document's
 * chain is active right now — for all five form types.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail(); // CS officer
    $this->studentBeta = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // IT Guild officer — different org
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->adviserOne = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
});

/**
 * Builds and dual-approves a registration directly for an org the actor is
 * ALREADY bound to (not via SubmitOrganizationRegistration, which now
 * requires a not-yet-affiliated founding student — Phase 2 item 5). These
 * tests are about view-authorization, not submission mechanics.
 */
function viewAuthApprovedRegistration(Organization $org, User $actor): void
{
    $engine = app(ApprovalEngine::class);
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();

    $doc = viewAuthRegistrationDocument($org, $actor);
    $engine->submit($doc, $actor);
    $doc->refresh();
    $engine->approve($doc, $sdaoA);
    $doc->refresh();
    $engine->approve($doc, $sdaoB);
}

function viewAuthRegistrationDocument(Organization $org, User $actor): Document
{
    $doc = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $actor->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'description' => 'Original description.',
        'contact_person' => 'Contact Person',
        'contact_number' => '09170000000',
        'contact_email' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
        'roster' => ['Member One'],
    ]);

    return $doc;
}

function viewAuthApprovedCalendarActivity(Organization $org, string $name): CalendarActivity
{
    $calendarDoc = Document::create([
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
        'document_id' => $calendarDoc->id,
        'academic_year' => AcademicYear::current(),
        'term' => 'first_term',
    ]);

    return CalendarActivity::create([
        'activity_calendar_id' => $cal->id,
        'name' => $name,
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

test('registration show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $doc = viewAuthRegistrationDocument($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->actingAs($this->studentAlpha)->get(route('registrations.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('registrations.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('registrations.show', $doc))->assertOk();
});

test('renewal show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    viewAuthApprovedRegistration($this->computingSociety, $this->studentAlpha);

    $doc = app(SubmitOrganizationRenewal::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        organizationType: OrganizationType::CoCurricular,
        description: 'Renewed description.',
        contactPerson: 'Contact Person',
        contactNumber: '09170000000',
        contactEmail: 'contact@example.test',
        dateOrganized: '2020-06-01',
        roster: ['Member One'],
    );

    $this->actingAs($this->studentAlpha)->get(route('renewals.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('renewals.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('renewals.show', $doc))->assertOk();
});

test('activity calendar show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $result = app(SubmitActivityCalendar::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        activities: [[
            'name' => 'Test Event',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]],
    );
    $doc = $result['document'];

    $this->actingAs($this->studentAlpha)->get(route('activity-calendars.show', $doc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('activity-calendars.show', $doc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('activity-calendars.show', $doc))->assertOk();
});

test('activity proposal show: org officer and the CURRENT step approver can view; different-org officer and a not-yet-current approver cannot', function () {
    $activity = viewAuthApprovedCalendarActivity($this->computingSociety, 'View Auth Activity');

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    // Regular on-calendar chain: step 1 = Adviser.
    expect($doc->current_step_position)->toBe(1);

    $this->actingAs($this->studentAlpha)->get(route('activity-proposals.show', $doc))->assertOk(); // (a) org officer
    $this->actingAs($this->studentBeta)->get(route('activity-proposals.show', $doc))->assertForbidden(); // different org
    $this->actingAs($this->adviserOne)->get(route('activity-proposals.show', $doc))->assertOk(); // (b) current-step approver
    $this->actingAs($this->chairCs)->get(route('activity-proposals.show', $doc))->assertForbidden(); // approver, but not their turn yet
});

test('after-activity report show: org officer can view, different-org officer cannot, current-step SDAO can', function () {
    $activity = viewAuthApprovedCalendarActivity($this->computingSociety, 'Report View Auth Activity');

    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->studentAlpha,
        organization: $this->computingSociety,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );
    ['document' => $proposalDoc] = app(SubmitActivityProposal::class)->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    foreach ([
        'adviser-one@sdao.test',
        'chair-cs@sdao.test',
        'dean-ccit@sdao.test',
        'sdao-a@sdao.test',
        'sdao-b@sdao.test',
        'asst-director@sdao.test',
        'academic-director@sdao.test',
        'executive-director@sdao.test',
    ] as $email) {
        $this->engine->approve($proposalDoc, User::where('email', $email)->firstOrFail());
        $proposalDoc->refresh();
    }

    $proposal = $proposalDoc->activityProposal()->firstOrFail();

    $reportDoc = app(SubmitAfterActivityReport::class)->execute(
        actor: $this->studentAlpha,
        proposal: $proposal,
        narrative: 'The activity happened as planned.',
    );

    $this->actingAs($this->studentAlpha)->get(route('reports.show', $reportDoc))->assertOk();
    $this->actingAs($this->studentBeta)->get(route('reports.show', $reportDoc))->assertForbidden();
    $this->actingAs($this->sdaoA)->get(route('reports.show', $reportDoc))->assertOk();
});
