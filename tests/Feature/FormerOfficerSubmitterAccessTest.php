<?php

use App\ActivityProposals\StartProposalDraft;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\ProposalCalendarMode;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Registrations\UpdateOrganizationRegistration;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * The submitted_by grant in OrganizationMembershipService::canActOnDocument()
 * is a founding-only exception: an OrganizationRegistration's submitter while
 * the org has no active officers. A removed officer's authorship grants
 * nothing once the org has officers — the roster decides who may act.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->updateAction = app(UpdateOrganizationRegistration::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->secretary = User::where('email', 'student-delta@students.nu-lipa.edu.ph')->firstOrFail();
});

function returnedRegistrationBy(Organization $org, ApprovalEngine $engine, User $submitter, User $sdao): Document
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
    $engine->returnForRevision($doc, $sdao, 'Please revise.');

    return $doc->refresh();
}

function removeOfficer(User $user): void
{
    $membership = OrganizationMembership::query()->where('user_id', $user->id)->active()->firstOrFail();
    app(OrganizationMembershipService::class)->close($membership);
}

// --- 1. A removed officer loses access once the org has active officers ---

test('a removed officer can no longer edit or resubmit a returned registration they submitted', function () {
    $doc = returnedRegistrationBy($this->org, $this->engine, $this->president, $this->sdaoA);
    removeOfficer($this->president);
    $former = $this->president->fresh();

    // The secretary is still an active officer, so the org is not "founding".
    expect(app(OrganizationMembershipService::class)->hasActiveOfficers($this->org))->toBeTrue();
    expect(Gate::forUser($former)->allows('edit', $doc))->toBeFalse();

    expect(fn () => $this->updateAction->execute(
        actor: $former,
        document: $doc,
        purposeOfOrganization: 'Edit by removed officer.',
        contactPerson: 'Former',
        contactNo: '09171234567',
        emailAddress: 'cs@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
    ))->toThrow(AuthorizationException::class);

    $this->actingAs($former)->post(route('attachments.store'), [
        'document_id' => $doc->id,
        'slot_key' => 'letter_of_intent',
        'file' => UploadedFile::fake()->create('loi.pdf', 50, 'application/pdf'),
    ])->assertForbidden();

    // The remaining officer keeps full access to the same document.
    expect(Gate::forUser($this->secretary)->allows('edit', $doc))->toBeTrue();
});

test('a removed officer can no longer continue or submit a draft proposal they started', function () {
    $draft = app(StartProposalDraft::class)->execute(
        actor: $this->president,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Removed Officer Draft',
            'venue' => 'Room 100',
            'activity_date' => '2026-11-20',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'term' => 'first_term',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );
    removeOfficer($this->president);
    $former = $this->president->fresh();

    $this->actingAs($former)->withoutVite()->get(route('activity-proposals.continue', $draft))->assertForbidden();
    $this->actingAs($former)->patch(route('activity-proposals.draft', $draft), ['objectives' => 'x'])->assertForbidden();
    $this->actingAs($former)->post(route('activity-proposals.submit', $draft), [
        'objectives' => 'Goal.',
        'activity_description' => 'Description.',
        'criteria_mechanics' => 'Criteria.',
        'program_flow' => 'Flow.',
        'expense_items' => [['material' => 'Expenses', 'quantity' => '1', 'unit_price' => '100.00']],
        'responsible_persons' => ['Responsible Person'],
    ])->assertForbidden();

    expect($draft->fresh()->status)->toBe(DocumentStatus::Draft);
});

test('document history and registrations list omit a removed officer\'s old documents', function () {
    $doc = returnedRegistrationBy($this->org, $this->engine, $this->president, $this->sdaoA);
    removeOfficer($this->president);
    $former = $this->president->fresh();

    $this->actingAs($former)->withoutVite()->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 0));

    $this->actingAs($former)->withoutVite()->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('registrations.data', 0));

    $this->actingAs($this->secretary)->withoutVite()->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)
            ->where('documents.data.0.id', $doc->id));
});

// --- 2. Founding student (org with no active officers) is unchanged ---

test('a founding student with no active officers can still view, edit and resubmit their registration', function () {
    $founder = User::factory()->create();
    $org = Organization::factory()->create();
    $doc = returnedRegistrationBy($org, $this->engine, $founder, $this->sdaoA);

    expect(app(OrganizationMembershipService::class)->hasActiveOfficers($org))->toBeFalse();
    expect(Gate::forUser($founder)->allows('view', $doc))->toBeTrue();
    expect(Gate::forUser($founder)->allows('edit', $doc))->toBeTrue();

    $resubmitted = $this->updateAction->execute(
        actor: $founder,
        document: $doc,
        purposeOfOrganization: 'Founder revision.',
        contactPerson: 'Founder',
        contactNo: '09171234567',
        emailAddress: 'founder@nu-lipa.edu.ph',
        dateOrganized: '2020-06-01',
        attachmentFiles: registrationAttachmentFiles(),
    );

    expect($resubmitted->fresh()->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($founder)->withoutVite()->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));
    $this->actingAs($founder)->withoutVite()->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('registrations.data', 1));
});

test('the founding exception is registration-only', function () {
    $founder = User::factory()->create();
    $org = Organization::factory()->create();
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityCalendar,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Returned,
        'submitted_by' => $founder->id,
    ]);

    expect(Gate::forUser($founder)->allows('edit', $doc))->toBeFalse();
});
