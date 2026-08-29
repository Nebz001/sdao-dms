<?php

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
