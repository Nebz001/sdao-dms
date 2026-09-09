<?php

use App\Enums\DocumentStatus;
use App\Enums\OrganizationType;
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
 * university-wide and has no college. HTTP-level coverage for what
 * StoreRegistrationRequest actually enforces around school/program selection.
 *
 * Structural fix (2026-09-09 plan): organization_type is no longer a
 * submitted, independently-validated field at all — posting one is inert,
 * the value in the request body is never read. Whether an org ends up
 * Co-Curricular or Extra-Curricular is now determined purely by whether a
 * school_id was submitted; OrganizationType::fromSchoolId() derives it at
 * write time. The corresponding action-class coverage lives in
 * SubmitRegistrationTest, and the resulting proposal-routing coverage lives
 * in ProposalVariantSelectionTest.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
});

test('a registration with no college computes an Extra-Curricular organization', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    expect($org->school_id)->toBeNull();
    expect($org->program_id)->toBeNull();
    $document = Document::where('organization_id', $org->id)->firstOrFail();
    expect($document->registrationDetail->organization_type)->toBe(OrganizationType::ExtraCurricular);
});

/**
 * Regression for a live production 500 (Singapore/Railway, 2026-08-30): every
 * non-academic registration failed while academic ones succeeded, because
 * 2026_08_29_130000_make_organizations_school_id_nullable had never been
 * applied there — organizations.school_id was still NOT NULL, so the
 * Organization::create() at SubmitOrganizationRegistration.php:113 threw
 * SQLSTATE[23502] for the null school_id that only the college-less path
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

test('a college-less registration submission enters the SDAO approval chain', function () {
    $student = User::factory()->create();

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['adviser_id' => unboundAdviserForAttachmentsTest()->id]),
        ['attachments' => registrationAttachmentFiles()],
    ))->assertSessionHasNoErrors();

    $organization = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $organization->id)->firstOrFail();

    expect($organization->school_id)->toBeNull()
        ->and($document->status)->toBe(DocumentStatus::InReview)
        ->and($document->workflow_template_id)->not->toBeNull()
        ->and($document->current_step_position)->toBe(1);
});

test('a registration at a regular school is rejected without a program', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'school_id' => $this->school->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['program_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

test('a registration at Senior High School succeeds without a program', function () {
    $student = User::factory()->create();
    $shs = School::where('type', 'senior_high')->firstOrFail();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'school_id' => $shs->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    expect($org->school_id)->toBe($shs->id);
    expect($org->program_id)->toBeNull();
});

test('a registration with no college is rejected if a program is submitted anyway', function () {
    $student = User::factory()->create();
    $program = $this->school->programs()->first() ?? Program::factory()->create(['school_id' => $this->school->id]);

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload([
            'program_id' => $program->id,
            'adviser_id' => unboundAdviserForAttachmentsTest()->id,
        ]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['program_id']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});
