<?php

use App\ActivityProposals\StartProposalDraft;
use App\Approval\ApprovalEngine;
use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Registrations\UpdateOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * Shared officer ownership: president and secretary are equal partners
 * (CLAUDE.md) — both may act on their org's documents, not just the literal
 * submitter. MembershipSeeder already binds Student Alpha as President and
 * Student Delta as Secretary of Computing Society specifically to exercise
 * this rule; Student Beta (IT Guild) is the ready-made different-org
 * negative case.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->updateAction = app(UpdateOrganizationRegistration::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@sdao.test')->firstOrFail();
    $this->differentOrgOfficer = User::where('email', 'student-beta@sdao.test')->firstOrFail(); // IT Guild
    $this->outsider = User::factory()->create();
});

function returnedRegistrationForOfficer(Organization $org, ApprovalEngine $engine, User $submitter, User $sdaoA): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();
    $engine->returnForRevision($doc, $sdaoA, 'Please revise your description.');
    $doc->refresh();

    return $doc;
}

// --- 1. Secretary can edit/resubmit a returned registration submitted by the president ---

test('secretary can edit and resubmit a returned registration submitted by the president', function () {
    $doc = returnedRegistrationForOfficer($this->org, $this->engine, $this->president, $this->sdaoA);

    expect(Gate::forUser($this->secretary)->allows('edit', $doc))->toBeTrue();

    $resubmitted = $this->updateAction->execute(
        actor: $this->secretary,
        document: $doc,
        organizationType: OrganizationType::ExtraCurricular,
        purposeOfOrganization: 'Updated by secretary.',
        contactPerson: 'Student Delta',
        contactNo: '09171234567',
        emailAddress: 'cs@sdao.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    $resubmitted->refresh();
    expect($resubmitted->status)->toBe(DocumentStatus::InReview);
    expect($resubmitted->registrationDetail->purpose_of_organization)->toBe('Updated by secretary.');
    // submitted_by is unchanged — still the president, the historical submitter.
    expect($resubmitted->submitted_by)->toBe($this->president->id);
});

// --- 2. Secretary can upload/delete an attachment on that returned document ---

test('secretary can upload and delete an attachment on the president\'s returned registration', function () {
    $doc = returnedRegistrationForOfficer($this->org, $this->engine, $this->president, $this->sdaoA);

    $response = $this->actingAs($this->secretary)->post(route('attachments.store'), [
        'document_id' => $doc->id,
        'slot_key' => 'letter_of_intent',
        'file' => UploadedFile::fake()->create('loi.pdf', 50, 'application/pdf'),
    ]);
    $response->assertCreated();

    $attachment = $doc->attachments()->where('slot_key', 'letter_of_intent')->firstOrFail();

    $this->actingAs($this->secretary)->delete(route('attachments.destroy', $attachment))->assertNoContent();
});

// --- 3. Secretary can continue, autosave, and submit the president's Draft proposal ---

test('secretary can continue, autosave, and submit the president\'s Draft proposal', function () {
    $document = app(StartProposalDraft::class)->execute(
        actor: $this->president,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Shared Ownership Test Activity',
            'venue' => 'Room 100',
            'activity_date' => '2026-11-20',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
    );

    $this->actingAs($this->secretary)->withoutVite()->get(route('activity-proposals.continue', $document))->assertOk();

    $this->actingAs($this->secretary)->patch(route('activity-proposals.draft', $document), [
        'objectives' => 'Objectives written by secretary.',
    ])->assertOk();
    expect($document->activityProposal->fresh()->objectives)->toBe('Objectives written by secretary.');

    $response = $this->actingAs($this->secretary)->post(route('activity-proposals.submit', $document), [
        'objectives' => 'Final objectives.',
        'narrative' => 'Narrative.',
        'criteria_mechanics' => 'Criteria.',
        'program_flow' => 'Flow.',
        'source_of_funding' => 'Funding.',
        'expense_items' => [['label' => 'Expenses', 'amount' => '100.00']],
    ]);
    $response->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::InReview);
    // submitted_by is unchanged — still the president, who started the draft.
    expect($document->submitted_by)->toBe($this->president->id);
});

// --- 4. An outsider and a different-org officer still 403 ---

test('an outsider still cannot edit another org\'s returned registration', function () {
    $doc = returnedRegistrationForOfficer($this->org, $this->engine, $this->president, $this->sdaoA);

    expect(Gate::forUser($this->outsider)->allows('edit', $doc))->toBeFalse();

    expect(fn () => $this->updateAction->execute(
        actor: $this->outsider,
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'Malicious edit.',
        contactPerson: 'Outsider',
        contactNo: '123',
        emailAddress: 'x@x.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});

test('a different-org officer still cannot edit or attach to this org\'s returned registration', function () {
    $doc = returnedRegistrationForOfficer($this->org, $this->engine, $this->president, $this->sdaoA);

    expect(Gate::forUser($this->differentOrgOfficer)->allows('edit', $doc))->toBeFalse();

    $this->actingAs($this->differentOrgOfficer)->post(route('attachments.store'), [
        'document_id' => $doc->id,
        'slot_key' => 'letter_of_intent',
        'file' => UploadedFile::fake()->create('loi.pdf', 50, 'application/pdf'),
    ])->assertForbidden();
});

// --- 5. An unverified co-officer still 403s ---

test('an unverified secretary still cannot edit the president\'s returned registration', function () {
    $doc = returnedRegistrationForOfficer($this->org, $this->engine, $this->president, $this->sdaoA);
    $this->secretary->update(['account_status' => AccountStatus::Unverified]);

    expect(Gate::forUser($this->secretary->fresh())->allows('edit', $doc))->toBeFalse();

    expect(fn () => $this->updateAction->execute(
        actor: $this->secretary->fresh(),
        document: $doc,
        organizationType: OrganizationType::CoCurricular,
        purposeOfOrganization: 'test',
        contactPerson: 'Test',
        contactNo: '123',
        emailAddress: 'test@test.com',
        dateOrganized: '2020-01-01',
    ))->toThrow(AuthorizationException::class);
});

// --- 6. The proposal index shows an org-mate's proposal ---

test('activity proposal index shows a document submitted by the org co-officer', function () {
    app(StartProposalDraft::class)->execute(
        actor: $this->president,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Index Visibility Test Activity',
            'venue' => 'Room 100',
            'activity_date' => '2026-11-21',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
    );

    $this->actingAs($this->secretary)
        ->withoutVite()
        ->get(route('activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/index')
            ->has('proposals', 1)
            ->where('proposals.0.title', fn (string $title) => str_contains($title, 'Index Visibility Test Activity')));
});
