<?php

use App\Approval\ApprovalEngine;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 2 item 8 — Registration's 6 required attachments: no conditionals on
 * Organization Type, submission blocked if any is missing.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->school = School::where('name', 'School of Computing and IT')->firstOrFail();
});

function foundingRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Attachments Test Org',
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Testing required attachments.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
    ], $overrides);
}

function unboundAdviserForAttachmentsTest(): User
{
    $adviser = User::factory()->create();
    RoleAssignment::create(['user_id' => $adviser->id, 'role' => Role::Adviser->value]);

    return $adviser;
}

/**
 * Slot labels sourced verbatim from the client's real form (sdao.md), same
 * wording as App\Attachments\AttachmentSlots::for(FormType::OrganizationRegistration).
 * Hardcoded here deliberately — this is a black-box HTTP-level test asserting
 * on the message the STUDENT actually sees, not on the production registry.
 *
 * @return array<string, string>
 */
function registrationAttachmentSlotLabels(): array
{
    return [
        'letter_of_intent' => 'Letter of Intent',
        'application_form' => 'Application Form',
        'by_laws' => 'By-Laws',
        'officers_list' => 'Updated List of Officers/Founders',
        'dean_endorsement_letter' => 'Letter from College Dean endorsing the Faculty Adviser',
        'proposed_projects_budget' => 'List of Proposed Projects with Budget',
    ];
}

test('store is blocked when any one of the 6 required attachments is missing, with an error naming the missing slot', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();
    $labels = registrationAttachmentSlotLabels();

    foreach (array_keys(registrationAttachmentFiles()) as $slotKey) {
        $files = registrationAttachmentFiles();
        unset($files[$slotKey]);

        $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
            foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
            ['attachments' => $files],
        ));

        // Content-checked, not just presence: the message must name this
        // exact slot's client-facing label (Laravel's assertInvalid matches
        // the given string as a substring of the actual message).
        $response->assertInvalid(["attachments.{$slotKey}" => $labels[$slotKey]]);
        expect(Organization::where('name', 'Attachments Test Org')->exists())->toBeFalse();
    }
});

test('store succeeds once all 6 required attachments are present', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $response->assertRedirect();

    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();

    expect($document->attachments()->count())->toBe(6);
    expect($document->attachments()->pluck('slot_key')->sort()->values()->all())->toBe([
        'application_form', 'by_laws', 'dean_endorsement_letter',
        'letter_of_intent', 'officers_list', 'proposed_projects_budget',
    ]);
});

test('a wrong-mime-type file is rejected by validation', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();

    $files = registrationAttachmentFiles();
    $files['letter_of_intent'] = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => $files],
    ));

    $response->assertInvalid(['attachments.letter_of_intent']);
});

test('an oversize file is rejected by validation', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();

    $files = registrationAttachmentFiles();
    $files['letter_of_intent'] = UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf'); // 20MB > 10MB cap

    $response = $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => $files],
    ));

    $response->assertInvalid(['attachments.letter_of_intent']);
});

test('resubmit preserves untouched slots and replaces a re-uploaded one', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();

    $originalLetterAttachment = $document->attachments()->where('slot_key', 'letter_of_intent')->firstOrFail();
    $originalByLawsAttachment = $document->attachments()->where('slot_key', 'by_laws')->firstOrFail();

    // SDAO returns it for revision.
    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    app(ApprovalEngine::class)->returnForRevision($document, $sdaoA, 'Please update the by-laws.');
    $document->refresh();

    // Resubmit with only by_laws re-uploaded — everything else untouched.
    $response = $this->actingAs($student)->put(route('registrations.update', $document), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Updated.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => [
            'by_laws' => UploadedFile::fake()->create('new-by-laws.pdf', 50, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect();
    $document->refresh();

    expect($document->attachments()->count())->toBe(6);

    // letter_of_intent untouched — same row, same file.
    expect(DocumentAttachment::find($originalLetterAttachment->id))->not->toBeNull();
    Storage::disk('local')->assertExists($originalLetterAttachment->path);

    // by_laws replaced — old file gone, new one in its place.
    expect(DocumentAttachment::find($originalByLawsAttachment->id))->toBeNull();
    Storage::disk('local')->assertMissing($originalByLawsAttachment->path);
    $newByLaws = $document->attachments()->where('slot_key', 'by_laws')->firstOrFail();
    expect($newByLaws->original_filename)->toBe('new-by-laws.pdf');
});

test('download is authorized for the submitter and the reviewing SDAO member, forbidden for an unrelated user', function () {
    $student = User::factory()->create();
    $adviser = unboundAdviserForAttachmentsTest();

    $this->actingAs($student)->post(route('registrations.store'), array_merge(
        foundingRegistrationPayload(['school_id' => $this->school->id, 'adviser_id' => $adviser->id]),
        ['attachments' => registrationAttachmentFiles()],
    ));

    $org = Organization::where('name', 'Attachments Test Org')->firstOrFail();
    $document = Document::where('organization_id', $org->id)->firstOrFail();
    $attachment = $document->attachments()->firstOrFail();

    $sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $outsider = User::factory()->create();

    $this->actingAs($student)->get(route('attachments.download', $attachment))->assertOk();
    $this->actingAs($sdaoA)->get(route('attachments.download', $attachment))->assertOk();
    $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
});
