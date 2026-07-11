<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Models\ActivityCalendar;
use App\Models\ActivityProposal;
use App\Models\CalendarActivity;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
});

/**
 * Binds an Unverified account as an active officer of $org, bypassing
 * BindOrganizationOfficer (which would itself reject an unverified student —
 * that path is covered separately in BindOfficerTest). This simulates the
 * defense-in-depth scenario: an active membership exists, but the account is
 * not (or is no longer) SDAO-Verified.
 */
function bindUnverifiedOfficerTo(Organization $org, string $position = 'secretary'): User
{
    $officer = User::factory()->unverifiedAccount()->create();

    OrganizationMembership::create([
        'user_id' => $officer->id,
        'organization_id' => $org->id,
        'position' => $position,
        'academic_year' => '2026-2027',
        'is_active' => true,
    ]);

    return $officer;
}

/**
 * A pre-approved on-calendar CalendarActivity for $org — the precondition an
 * activity proposal (and, in turn, an after-activity report) needs.
 */
function approvedCalendarActivityForVerificationGate(Organization $org): CalendarActivity
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
        'name' => 'Verification Gate Test Activity',
        'venue' => 'Main Hall',
        'activity_date' => '2026-10-30',
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);
}

/**
 * Drives a fresh on-calendar proposal for $org through its full regular-school
 * 7-step chain to Approved, returning the ActivityProposal — the precondition
 * an after-activity report needs (CLAUDE.md: hard-linked to an APPROVED activity).
 */
function approvedProposalForVerificationGate(Organization $org, User $student): ActivityProposal
{
    $activity = approvedCalendarActivityForVerificationGate($org);

    $draft = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $org,
        mode: ProposalCalendarMode::OnCalendar,
        data: ['calendar_activity_id' => $activity->id],
    );

    ['document' => $doc] = app(SubmitActivityProposal::class)->execute(
        actor: $student,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    );

    $engine = app(ApprovalEngine::class);
    foreach ([
        'adviser-one@sdao.test',
        'chair-cs@sdao.test',
        'dean-ccit@sdao.test',
        'sdao-a@sdao.test',
        'sdao-b@sdao.test',
        'asst-director@sdao.test',
        'academic-director@sdao.test',
        'executive-director@sdao.test',
    ] as $approverEmail) {
        $engine->approve($doc, User::where('email', $approverEmail)->firstOrFail());
        $doc->refresh();
    }

    expect($doc->status)->toBe(DocumentStatus::Approved);

    return $doc->activityProposal()->firstOrFail();
}

test('OrganizationMembershipService treats an unverified account as having no active membership', function () {
    $unverified = User::factory()->unverifiedAccount()->create();
    OrganizationMembership::create([
        'user_id' => $unverified->id,
        'organization_id' => $this->org->id,
        'position' => 'president',
        'academic_year' => '2026-2027',
        'is_active' => true,
    ]);

    expect(app(OrganizationMembershipService::class)->activeMembershipFor($unverified, $this->org))->toBeNull();
});

test('OrganizationMembershipService treats a rejected account as having no active membership', function () {
    $rejected = User::factory()->rejectedAccount()->create();
    OrganizationMembership::create([
        'user_id' => $rejected->id,
        'organization_id' => $this->org->id,
        'position' => 'secretary',
        'academic_year' => '2026-2027',
        'is_active' => true,
    ]);

    expect(app(OrganizationMembershipService::class)->activeMembershipFor($rejected, $this->org))->toBeNull();
});

test('an unverified officer is forbidden from proposing a new organization', function () {
    $officer = User::factory()->unverifiedAccount()->create();

    // Phase 2 item 5: registration is now a founding proposal — the payload
    // must be schema-valid so the block comes from Gate::authorize('propose')
    // (isVerifiedAccount), not incidental FormRequest validation noise.
    $response = $this->actingAs($officer)->post(route('registrations.store'), [
        'name' => 'Should Never Be Created',
        'school_id' => $this->org->school_id,
        'adviser_id' => $this->sdaoA->id,
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Should never be created.',
        'contact_person' => 'Someone',
        'contact_no' => '09170000000',
        'email_address' => 'someone@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => registrationAttachmentFiles(),
    ]);

    $response->assertForbidden();
    expect(Organization::where('name', 'Should Never Be Created')->exists())->toBeFalse();
});

test('a rejected officer is forbidden from proposing a new organization', function () {
    $officer = User::factory()->rejectedAccount()->create();

    $response = $this->actingAs($officer)->post(route('registrations.store'), [
        'name' => 'Should Never Be Created Either',
        'school_id' => $this->org->school_id,
        'adviser_id' => $this->sdaoA->id,
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Should never be created.',
        'contact_person' => 'Someone',
        'contact_no' => '09170000000',
        'email_address' => 'someone@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => registrationAttachmentFiles(),
    ]);

    $response->assertForbidden();
    expect(Organization::where('name', 'Should Never Be Created Either')->exists())->toBeFalse();
});

test('the activity-proposal chain-entry submit is forbidden once the account is no longer verified', function () {
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    $document = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Draft Test Activity',
            'venue' => 'Room 200',
            'activity_date' => '2026-12-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
    );

    // Simulate SDAO later rejecting the account after the draft was started.
    $student->update(['account_status' => AccountStatus::Rejected]);

    $response = $this->actingAs($student)->post(route('activity-proposals.submit', $document), [
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria/Mechanics',
        'program_flow' => 'Program Flow',
        'source_of_funding' => 'Source of Funding',
        'expenses' => 'Expenses',
    ]);

    $response->assertForbidden();
    expect($document->fresh()->status->value)->toBe('draft');
});

test('the activity-proposal chain-entry submit is forbidden when the account was never verified', function () {
    $student = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    $document = app(StartProposalDraft::class)->execute(
        actor: $student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Draft Test Activity',
            'venue' => 'Room 200',
            'activity_date' => '2026-12-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
    );

    // Simulate the account never having been SDAO-verified in the first place.
    $student->update(['account_status' => AccountStatus::Unverified]);

    $response = $this->actingAs($student)->post(route('activity-proposals.submit', $document), [
        'objectives' => 'Objectives',
        'narrative' => 'Narrative',
        'criteria_mechanics' => 'Criteria/Mechanics',
        'program_flow' => 'Program Flow',
        'source_of_funding' => 'Source of Funding',
        'expenses' => 'Expenses',
    ]);

    $response->assertForbidden();
    expect($document->fresh()->status->value)->toBe('draft');
});

test('an unverified officer is forbidden from submitting a renewal', function () {
    // Renewal requires a prior Approved registration for the org first.
    $verifiedOfficer = User::where('email', 'student-alpha@sdao.test')->firstOrFail();

    // Built directly (not via SubmitOrganizationRegistration, which now
    // requires a not-yet-affiliated founding student — Phase 2 item 5): this
    // fixture only needs a prior Approved registration for an org the
    // officer is already bound to.
    $registration = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$this->org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => $verifiedOfficer->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $registration->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Original Person',
        'contact_no' => '09171111111',
        'email_address' => 'original@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $engine = app(ApprovalEngine::class);
    $engine->submit($registration, $verifiedOfficer);
    $registration->refresh();
    $engine->approve($registration, User::where('email', 'sdao-a@sdao.test')->firstOrFail());
    $registration->refresh();
    $engine->approve($registration, User::where('email', 'sdao-b@sdao.test')->firstOrFail());
    $registration->refresh();
    expect($registration->status)->toBe(DocumentStatus::Approved);

    $officer = bindUnverifiedOfficerTo($this->org);

    $response = $this->actingAs($officer)->post(route('renewals.store'), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Renewed description.',
        'contact_person' => 'Renewed Contact',
        'contact_no' => '09172222222',
        'email_address' => 'renewed@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => renewalAttachmentFiles(),
    ]);

    $response->assertForbidden();
    expect(Document::where('form_type', FormType::OrganizationRenewal->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeFalse();
});

test('an unverified officer is forbidden from submitting an activity calendar', function () {
    $officer = bindUnverifiedOfficerTo($this->org);

    $response = $this->actingAs($officer)->post(route('activity-calendars.store'), [
        'activities' => [[
            'name' => 'JS Night',
            'venue' => 'Gymnasium',
            'activity_date' => '2026-09-15',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'description' => 'JavaScript showcase.',
            // Required by StoreActivityCalendarRequest (Phase 2 item 7 slice 1)
            // — without these the request would 422 on validation before ever
            // reaching the authorization check this test is actually exercising.
            'sdg' => 'quality_education',
            'participant_program_assigned' => 'All Year Levels',
            'budget' => '5000.00',
        ]],
    ]);

    $response->assertForbidden();
    expect(Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeFalse();
});

test('an unverified officer is forbidden from creating an activity-proposal draft (step 1)', function () {
    $officer = bindUnverifiedOfficerTo($this->org);

    $response = $this->actingAs($officer)->post(route('activity-proposals.store'), [
        'calendar_mode' => 'off_calendar',
        'title' => 'Should Never Draft',
        'venue' => 'Room 200',
        'activity_date' => '2026-12-01',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'term' => 'first_term',
        // Required by StoreProposalStepOneRequest (Phase 2 item 7 slice 4a)
        // — without these the request would 422 on validation before ever
        // reaching the authorization check this test is actually exercising.
        'activity_nature' => 'co_curricular',
        'activity_type' => 'seminar_workshop',
        'partner_organizations' => ['Partner Org'],
        'target_sdg' => 'quality_education',
        'proposed_budget' => '5000.00',
        'budget_source' => 'Org funds',
    ]);

    $response->assertForbidden();
    expect(Document::where('form_type', FormType::ActivityProposal->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeFalse();
});

test('an unverified officer is forbidden from submitting an after-activity report', function () {
    $verifiedOfficer = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $proposal = approvedProposalForVerificationGate($this->org, $verifiedOfficer);

    $officer = bindUnverifiedOfficerTo($this->org);

    $response = $this->actingAs($officer)->post(route('reports.store'), [
        'activity_proposal_id' => $proposal->id,
        'summary' => 'Should never be created.',
        // Required by StoreReportRequest (Phase 2 item 7 slice 3) — without
        // these the request would 422 on validation before ever reaching
        // the authorization check this test is actually exercising.
        'activity_chairs' => ['Chair Name'],
        'prepared_by' => 'Preparer Name',
        'event_program' => 'Program details.',
        'target_participants_percentage' => 80,
        'attachments' => reportAttachmentFiles(),
    ]);

    $response->assertForbidden();
    expect(Document::where('form_type', FormType::AfterActivityReport->value)
        ->where('organization_id', $this->org->id)
        ->exists())->toBeFalse();
});
