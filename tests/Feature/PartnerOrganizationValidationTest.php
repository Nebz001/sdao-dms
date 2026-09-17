<?php

use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * StoreProposalStepOneRequest's partner_organizations.*.organization_id rule
 * — covers the two edges the shape change introduced: an empty-string hidden
 * input (the free-text fallback, since PartnerOrganizationRow always submits
 * a hidden organization_id input alongside the visible name field) must
 * persist as null, not fail validation or coerce to 0; and a tampered/stale
 * id must fail exists(), not silently save a dangling reference.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
});

function latestPartnerOrgTestDocument(Organization $org): Document
{
    // StartProposalDraft prefixes the stored title for off-calendar mode
    // ("Activity Proposal — {title} ({org})"), so look up by form_type +
    // organization_id instead — the pattern already established in
    // ResubmitRedirectRegressionTest.
    return Document::where('form_type', FormType::ActivityProposal->value)
        ->where('organization_id', $org->id)
        ->latest('id')
        ->firstOrFail();
}

function partnerOrgStep1Payload(array $partnerOrganizations): array
{
    return [
        'calendar_mode' => 'off_calendar',
        'title' => 'Partner Org Validation Test Activity',
        'venue' => 'Room 700',
        'activity_date' => '2026-12-01',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'activity_nature' => 'co_curricular',
        'activity_type' => 'seminar_workshop',
        'partner_organizations' => $partnerOrganizations,
        'target_sdg' => ['quality_education'],
        'proposed_budget' => '5000.00',
        'budget_source' => 'rso_fund',
        'attachments' => proposalStepOneAttachmentFiles(),
    ];
}

test('an empty-string organization_id — the free-text fallback\'s hidden input — persists as null, not an error', function () {
    // This is exactly what PartnerOrganizationRow submits for a free-text
    // entry: the hidden input always renders, value={organizationId ?? ''}.
    $response = $this->actingAs($this->studentAlpha)->post(
        route('activity-proposals.store'),
        partnerOrgStep1Payload([
            ['organization_id' => '', 'name' => 'An Unregistered RSO'],
        ]),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $document = latestPartnerOrgTestDocument($this->org);
    expect($document->activityProposal->partner_organizations)->toBe([
        ['name' => 'An Unregistered RSO', 'organization_id' => null],
    ]);
});

test('a real organization_id persists the link', function () {
    $partner = Organization::where('name', 'IT Guild')->firstOrFail();

    $response = $this->actingAs($this->studentAlpha)->post(
        route('activity-proposals.store'),
        partnerOrgStep1Payload([
            ['organization_id' => $partner->id, 'name' => $partner->name],
        ]),
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $document = latestPartnerOrgTestDocument($this->org);
    expect($document->activityProposal->partner_organizations)->toBe([
        ['name' => 'IT Guild', 'organization_id' => $partner->id],
    ]);
});

test('an organization_id that does not exist fails validation instead of saving a dangling reference', function () {
    $response = $this->actingAs($this->studentAlpha)->post(
        route('activity-proposals.store'),
        partnerOrgStep1Payload([
            ['organization_id' => 999999, 'name' => 'Tampered Entry'],
        ]),
    );

    $response->assertInvalid(['partner_organizations.0.organization_id']);
    expect(Document::where('form_type', FormType::ActivityProposal->value)->where('organization_id', $this->org->id)->exists())->toBeFalse();
});

test('a mix of a linked entry and a free-text entry on the same submission both persist correctly', function () {
    $partner = Organization::where('name', 'IT Guild')->firstOrFail();

    $response = $this->actingAs($this->studentAlpha)->post(
        route('activity-proposals.store'),
        partnerOrgStep1Payload([
            ['organization_id' => $partner->id, 'name' => $partner->name],
            ['organization_id' => '', 'name' => 'A Free-Text Partner'],
        ]),
    );

    $response->assertSessionHasNoErrors();

    $document = latestPartnerOrgTestDocument($this->org);
    expect($document->activityProposal->partner_organizations)->toBe([
        ['name' => 'IT Guild', 'organization_id' => $partner->id],
        ['name' => 'A Free-Text Partner', 'organization_id' => null],
    ]);
});
