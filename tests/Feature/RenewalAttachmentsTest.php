<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 2 item 8 — Renewal's 9 required attachments (Registration's 6 plus 3
 * more): no conditionals, submission blocked if any is missing.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

/**
 * Submits and dual-approves a prior registration directly (bypassing
 * SubmitOrganizationRegistration, which now requires a not-yet-affiliated
 * founding student) so the renewal's precondition is satisfied.
 */
function approvedPriorRegistrationFor(Organization $org, User $actor): void
{
    $document = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $actor->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $document->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Original Person',
        'contact_no' => '09171111111',
        'email_address' => 'original@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);

    $engine = app(ApprovalEngine::class);
    $engine->submit($document, $actor);
    $document->refresh();
    $engine->approve($document, User::where('email', 'sdao-a@sdao.test')->firstOrFail());
    $document->refresh();
    $engine->approve($document, User::where('email', 'sdao-b@sdao.test')->firstOrFail());
}

function renewalStorePayload(array $overrides = []): array
{
    return array_merge([
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Renewed description.',
        'contact_person' => 'Renewed Contact',
        'contact_no' => '09172222222',
        'email_address' => 'renewed@example.test',
        'date_organized' => '2020-06-01',
    ], $overrides);
}

/**
 * Slot labels sourced verbatim from the client's real form (sdao.md), same
 * wording as App\Attachments\AttachmentSlots::for(FormType::OrganizationRenewal)
 * (Registration's 6 plus these 3). Hardcoded here deliberately — this is a
 * black-box HTTP-level test asserting on the message the STUDENT actually
 * sees, not on the production registry.
 *
 * @return array<string, string>
 */
function renewalAttachmentSlotLabels(): array
{
    return [
        'letter_of_intent' => 'Letter of Intent',
        'application_form' => 'Application Form',
        'by_laws' => 'By-Laws',
        'officers_list' => 'Updated List of Officers/Founders',
        'dean_endorsement_letter' => 'Letter from College Dean endorsing the Faculty Adviser',
        'proposed_projects_budget' => 'List of Proposed Projects with Budget',
        'past_projects_list' => 'List of Past Projects',
        'financial_statement' => 'Financial Statement',
        'evaluation_summary' => 'Summary of Evaluation',
    ];
}

test('store is blocked when any one of the 9 required attachments is missing, with an error naming the missing slot', function () {
    approvedPriorRegistrationFor($this->org, $this->studentAlpha);
    $labels = renewalAttachmentSlotLabels();

    foreach (array_keys(renewalAttachmentFiles()) as $slotKey) {
        $files = renewalAttachmentFiles();
        unset($files[$slotKey]);

        $response = $this->actingAs($this->studentAlpha)->post(route('renewals.store'), array_merge(
            renewalStorePayload(),
            ['attachments' => $files],
        ));

        // Content-checked, not just presence: the message must name this
        // exact slot's client-facing label.
        $response->assertInvalid(["attachments.{$slotKey}" => $labels[$slotKey]]);
    }

    expect(Document::where('form_type', FormType::OrganizationRenewal->value)->exists())->toBeFalse();
});

test('store succeeds once all 9 required attachments are present', function () {
    approvedPriorRegistrationFor($this->org, $this->studentAlpha);

    $response = $this->actingAs($this->studentAlpha)->post(route('renewals.store'), array_merge(
        renewalStorePayload(),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $response->assertRedirect();

    $renewal = Document::where('form_type', FormType::OrganizationRenewal->value)->firstOrFail();
    expect($renewal->attachments()->count())->toBe(9);
});

test('resubmit preserves untouched slots and replaces a re-uploaded one', function () {
    approvedPriorRegistrationFor($this->org, $this->studentAlpha);

    $this->actingAs($this->studentAlpha)->post(route('renewals.store'), array_merge(
        renewalStorePayload(),
        ['attachments' => renewalAttachmentFiles()],
    ));

    $renewal = Document::where('form_type', FormType::OrganizationRenewal->value)->firstOrFail();
    $originalFinancialStatement = $renewal->attachments()->where('slot_key', 'financial_statement')->firstOrFail();

    app(ApprovalEngine::class)->returnForRevision($renewal, $this->sdaoA, 'Please update the financial statement.');
    $renewal->refresh();

    $response = $this->actingAs($this->studentAlpha)->put(route('renewals.update', $renewal), [
        'organization_type' => 'co_curricular',
        'purpose_of_organization' => 'Renewed description.',
        'contact_person' => 'Renewed Contact',
        'contact_no' => '09172222222',
        'email_address' => 'renewed@example.test',
        'date_organized' => '2020-06-01',
        'attachments' => [
            'financial_statement' => UploadedFile::fake()->create('new-financials.pdf', 50, 'application/pdf'),
        ],
    ]);

    $response->assertRedirect();
    $renewal->refresh();

    expect($renewal->attachments()->count())->toBe(9);
    expect(DocumentAttachment::find($originalFinancialStatement->id))->toBeNull();
    Storage::disk('local')->assertMissing($originalFinancialStatement->path);
});
