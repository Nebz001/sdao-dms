<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Unlike registration, organization_type is a re-submitted, user-editable
 * field on renewal (StoreRenewalRequest previously had no withValidator() at
 * all). A renewal choosing a type that contradicts the organization's
 * existing, immutable school_id/program_id would recreate the exact
 * data-integrity violation the 2026_09_09_100000 migration corrects — see the
 * fix plan and ExtraCurricularRegistrationTest (the sibling registration-side
 * coverage). Helpers (`approvedPriorRegistrationFor`, `renewalStorePayload`,
 * `renewalAttachmentFiles`) come from RenewalAttachmentsTest.php.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
});

test('a renewal cannot switch a school-affiliated org to Extra-Curricular', function () {
    $org = Organization::where('name', 'Computing Society')->firstOrFail();
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    approvedPriorRegistrationFor($org, $student);

    $response = $this->actingAs($student)->post(route('renewals.store'), array_merge(
        renewalStorePayload(['organization_type' => 'extra_curricular']),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertInvalid(['organization_type']);
});

test('a renewal cannot switch a college-less org to Co-Curricular', function () {
    $org = Organization::where('name', 'University Chess Club')->firstOrFail();
    $student = User::where('email', 'student-epsilon@students.nu-lipa.edu.ph')->firstOrFail();
    approvedPriorRegistrationFor($org, $student);

    $response = $this->actingAs($student)->post(route('renewals.store'), array_merge(
        renewalStorePayload(['organization_type' => 'co_curricular']),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertInvalid(['organization_type']);
});
