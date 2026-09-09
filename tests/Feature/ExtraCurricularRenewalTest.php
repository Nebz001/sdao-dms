<?php

use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Structural fix (2026-09-09 plan): organization_type is no longer a
 * submitted, independently-editable field on renewal at all — it's derived
 * from the organization's school_id (OrganizationType::fromOrganization()),
 * computed once at write time. There is nothing left to "switch" or reject;
 * these tests instead pin that the derivation is correct on the real HTTP
 * store path, for both shapes. Helpers (`approvedPriorRegistrationFor`,
 * `renewalStorePayload`, `renewalAttachmentFiles`) come from
 * RenewalAttachmentsTest.php.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('renewing a school-affiliated org computes Co-Curricular regardless of what is posted', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    approvedPriorRegistrationFor($org, $student);

    // Posting a contradicting organization_type has no effect at all — it's
    // not a validated or read field anymore.
    $response = $this->actingAs($student)->post(route('renewals.store'), array_merge(
        renewalStorePayload(['organization_type' => 'extra_curricular']),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    $document = Document::where('organization_id', $org->id)
        ->where('form_type', 'organization_renewal')
        ->firstOrFail();
    expect($document->registrationDetail->organization_type)->toBe(OrganizationType::CoCurricular);
});

test('renewing a college-less org computes Extra-Curricular regardless of what is posted', function () {
    $org = Organization::where('name', 'University Chess Club')->firstOrFail();
    $student = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail();
    approvedPriorRegistrationFor($org, $student);

    $response = $this->actingAs($student)->post(route('renewals.store'), array_merge(
        renewalStorePayload(['organization_type' => 'co_curricular']),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertSessionHasNoErrors();
    $document = Document::where('organization_id', $org->id)
        ->where('form_type', 'organization_renewal')
        ->firstOrFail();
    expect($document->registrationDetail->organization_type)->toBe(OrganizationType::ExtraCurricular);
});
