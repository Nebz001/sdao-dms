<?php

use App\Approval\ApprovalEngine;
use App\Models\Document;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * HTTP-level coverage for the adviser typeahead's "bypass the UI" path: what
 * StoreRegistrationRequest / UpdateRegistrationRequest actually do when an
 * adviser_id reaches the backend without ever being a real, admin-provisioned
 * adviser account — either because the id doesn't exist at all, or because it
 * belongs to a real user who just isn't an adviser. Both must be rejected with
 * a clear adviser_id validation error, never a 500.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
});

test('store rejects a completely non-existent adviser_id with a clear error', function () {
    $student = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => 999999]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['adviser_id' => 'Select an adviser from the search list.']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

test('store rejects a real user who does not hold Role::Adviser with a clear error', function () {
    $student = User::factory()->create();
    $notAnAdviser = User::factory()->create();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $notAnAdviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertInvalid(['adviser_id' => 'Select an adviser from the search list.']);
    expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
});

test('resubmit rejects picking a non-adviser account as the new adviser', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();
    $notAnAdviser = User::factory()->create();

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();

    $sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    app(ApprovalEngine::class)->returnForRevision($document, $sdaoA, 'Please pick a different adviser.');
    $document->refresh();

    $response = $this->actingAs($student)->put(route('registrations.update', $document), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Updated.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => $notAnAdviser->id,
    ]);

    $response->assertInvalid(['adviser_id' => 'Select an adviser from the search list.']);
    expect($document->fresh()->registrationDetail->adviser_id)->toBe($adviser->id);
});

test('the adviser-search endpoint never lists a non-adviser account, and reports no matches for a bogus query', function () {
    $student = User::factory()->create();
    unboundAdviserForAttachmentsTest(); // an unrelated real adviser, should not match below

    $response = $this->actingAs($student)->getJson(route('registrations.adviser-search', ['q' => 'zzz-no-such-person-zzz']));

    $response->assertOk();
    expect($response->json('advisers'))->toBe([]);
});
