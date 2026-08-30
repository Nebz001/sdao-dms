<?php

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Program;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 remediation item 3 — an Extra-Curricular organization is
 * university-wide and has no college. HTTP-level coverage for
 * StoreRegistrationRequest's conditional school_id requirement; the
 * corresponding action-class coverage lives in SubmitRegistrationTest, and
 * the resulting proposal-routing coverage lives in
 * ProposalVariantSelectionTest.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
});

test('a Co-Curricular registration is rejected without a college', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['organization_type' => 'co_curricular', 'adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['school_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

test('an Extra-Curricular registration succeeds with no college', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['organization_type' => 'extra_curricular', 'adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    expect($org->school_id)->toBeNull();
    expect($org->program_id)->toBeNull();
});

test('an Extra-Curricular registration is rejected if a college is also submitted', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'organization_type' => 'extra_curricular',
            'school_id' => $this->school->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['school_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

/**
 * Regression for a live production 500 (Singapore/Railway, 2026-08-30): every
 * non-academic registration failed while academic ones succeeded, because
 * 2026_08_29_130000_make_organizations_school_id_nullable had never been
 * applied there — organizations.school_id was still NOT NULL, so the
 * Organization::create() at SubmitOrganizationRegistration.php:113 threw
 * SQLSTATE[23502] for the null school_id that only the Extra-Curricular path
 * supplies. The two tests below pin the schema invariant and the full
 * submission respectively, so a reverted/unapplied migration fails here
 * rather than only in production.
 */
test('an organization can be persisted with no college', function () {
    $organization = Organization::create([
        'name' => 'College-less Org',
        'school_id' => null,
        'program_id' => null,
    ]);

    expect($organization->refresh()->school_id)->toBeNull();
});

test('an Extra-Curricular registration submission enters the SDAO approval chain', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['organization_type' => 'extra_curricular', 'adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ))->assertSessionHasNoErrors();

    $organization = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $organization->id)->firstOrFail();

    expect($organization->school_id)->toBeNull()
        ->and($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->workflow_template_id)->not->toBeNull()
        ->and($document->current_step_position)->toBe(1);
});

test('an Extra-Curricular registration is rejected if a program is also submitted', function () {
    $student = User::factory()->create();
    $program = $this->school->programs()->first() ?? Program::factory()->create(['school_id' => $this->school->id]);

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'organization_type' => 'extra_curricular',
            'program_id' => $program->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['program_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});
