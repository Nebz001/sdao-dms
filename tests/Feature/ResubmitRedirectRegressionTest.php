<?php

use App\Approval\ApprovalEngine;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Closes the coverage gap flagged during the Group B item 4 investigation
 * ("save and submit revision" reportedly redirects to a 403): no existing
 * test exercised the full HTTP round trip for any of the five resubmit
 * flows — submit the PUT, capture the redirect Laravel returns, follow it
 * with a real GET, and check that GET actually succeeds for the submitting
 * user. Every existing HTTP-level resubmit test stops at ->assertRedirect(),
 * and ReturnAndResubmitActivityCalendarTest / ReturnAndResubmitTest /
 * ProposalReturnForRevisionTest call the action classes directly, bypassing
 * HTTP and the Gate entirely.
 *
 * Each test below: submit -> SDAO (or the current-step approver) returns for
 * revision -> resubmit via a real PUT as the ORIGINAL SUBMITTER -> follow the
 * redirect with a real GET -> assert 200, not 403.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
});

/**
 * PUTs the resubmission, asserts Laravel actually redirected (not a 403 on
 * the PUT itself — a distinct, separate failure mode from the one under
 * investigation), then follows that exact redirect target with a real GET
 * as the same user and asserts it succeeds.
 */
function assertResubmitRedirectSucceeds(User $actor, string $updateUrl, array $payload): void
{
    $putResponse = test()->actingAs($actor)->put($updateUrl, $payload);
    $putResponse->assertRedirect();

    $location = $putResponse->headers->get('Location');
    test()->actingAs($actor)->get($location)->assertOk();
}

test('resubmitting a returned registration: the redirect target loads for the submitter', function () {
    $student = User::factory()->create();
    $school = School::where('name', 'School of Computing and IT')->firstOrFail();
    $program = Program::where('name', 'BS Computer Science')->firstOrFail();
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'school_id' => $school->id,
            'program_id' => $program->id,
            'adviser_id' => $adviser->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ))->assertRedirect();

    $document = Document::where('form_type', FormType::OrganizationRegistration->value)
        ->where('submitted_by', $student->id)
        ->firstOrFail();

    app(ApprovalEngine::class)->returnForRevision($document, $this->sdaoA, 'Please revise.');
    $document->refresh();

    assertResubmitRedirectSucceeds($student, route('registrations.update', $document), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Updated.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
    ]);
});

test('resubmitting a returned renewal: the redirect target loads for the submitter', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    approvedPriorRegistrationFor($org, $student);

    $this->actingAs($student)->post(route('renewals.store'), array_merge(
        renewalStorePayload(),
        ['attachments' => renewalAttachmentFiles()],
    ))->assertRedirect();

    $document = Document::where('form_type', FormType::OrganizationRenewal->value)
        ->where('organization_id', $org->id)
        ->firstOrFail();

    app(ApprovalEngine::class)->returnForRevision($document, $this->sdaoA, 'Please revise.');
    $document->refresh();

    assertResubmitRedirectSucceeds($student, route('renewals.update', $document), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Renewed description.',
        'contact_person' => 'Renewed Contact',
        'contact_no' => '09172222222',
        'email_address' => 'renewed@example.test',
        'date_organized' => '2020-06-01',
    ]);
});

test('resubmitting a returned activity calendar: the redirect target loads for the submitter', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $activityPayload = [
        'name' => 'Draft Event',
        'venue' => 'Gymnasium',
        'activity_date' => '2026-09-15',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'sdg' => ['quality_education'],
        'participant_program_assigned' => 'All Year Levels',
        'budget' => '5000.00',
    ];

    $this->actingAs($student)->post(route('activity-calendars.store'), [
        'activities' => [$activityPayload],
    ])->assertRedirect();

    $document = Document::where('form_type', FormType::ActivityCalendar->value)
        ->where('organization_id', $org->id)
        ->firstOrFail();

    app(ApprovalEngine::class)->returnForRevision($document, $this->sdaoA, 'Please update the venue.');
    $document->refresh();

    assertResubmitRedirectSucceeds($student, route('activity-calendars.update', $document), [
        'activities' => [array_merge($activityPayload, ['venue' => 'Auditorium'])],
    ]);
});

test('resubmitting a returned activity proposal: the redirect target loads for the submitter', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();

    $step1Payload = [
        'calendar_mode' => 'off_calendar',
        'title' => 'Resubmit Redirect Test Activity',
        'venue' => 'Room 300',
        'activity_date' => '2026-11-01',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'activity_nature' => 'co_curricular',
        'activity_type' => 'seminar_workshop',
        'partner_organizations' => ['Partner Org A'],
        'target_sdg' => ['quality_education'],
        'proposed_budget' => '15000.00',
        'budget_source' => 'rso_fund',
        'attachments' => proposalStepOneAttachmentFiles(),
    ];

    $storeResponse = $this->actingAs($student)->post(route('activity-proposals.store'), $step1Payload);
    $storeResponse->assertRedirect();

    $document = Document::where('form_type', FormType::ActivityProposal->value)
        ->where('organization_id', $org->id)
        ->latest('id')
        ->firstOrFail();

    $this->actingAs($student)->post(route('activity-proposals.submit', $document), [
        'overall_goal' => 'Overall Goal',
        'specific_objectives' => 'Specific Objectives',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'expense_items' => [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
    ])->assertRedirect();
    $document->refresh();

    // Adviser is step 1 regardless of calendar mode (invariant #8).
    $adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    app(ApprovalEngine::class)->returnForRevision($document, $adviser, 'Please fix the schedule.');
    $document->refresh();

    assertResubmitRedirectSucceeds($student, route('activity-proposals.update', $document), [
        'overall_goal' => 'Updated overall goal',
        'specific_objectives' => 'Updated specific objectives',
        'criteria_mechanics' => 'Criteria',
        'program_flow' => 'Program flow',
        'expense_items' => [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
        'title' => 'Resubmit Redirect Test Activity',
        'venue' => 'Room 400',
        'activity_date' => '2026-12-10',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
});

test('resubmitting a returned after-activity report: the redirect target loads for the submitter', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $proposal = attachmentsTestApprovedProposal($org, $student);

    $this->actingAs($student)->post(route('reports.store'), array_merge(
        attachmentsTestReportStorePayload($proposal->id),
        ['attachments' => reportAttachmentFiles()],
    ))->assertRedirect();

    $document = Document::where('form_type', FormType::AfterActivityReport->value)
        ->where('organization_id', $org->id)
        ->firstOrFail();

    app(ApprovalEngine::class)->returnForRevision($document, $this->sdaoA, 'Please fix the evaluation form.');
    $document->refresh();

    assertResubmitRedirectSucceeds($student, route('reports.update', $document), [
        'summary' => 'The activity happened as planned.',
        'activity_chairs' => ['Chair One'],
        'prepared_by' => 'Preparer Name',
        'event_program' => 'Program details.',
        'target_participants_percentage' => 85,
    ]);
});
