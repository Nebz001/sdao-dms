<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Phase 2 item 9 — proves the student-facing edit() page's `flaggedSections`
 * prop reflects exactly the sections flagged by the return that put the
 * document in its CURRENT Returned state (App\Approval\SectionFlags::
 * currentlyFlagged), not a union across every return the document has ever
 * had.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
});

function highlightTestSubmittedRegistration(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $doc->id]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

test('edit page flaggedSections matches exactly the sections flagged on return', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Fix these two things.',
        ['contact_information', 'attachments'],
    );
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedSections', ['contact_information', 'attachments'])
        );
});

test('edit page flaggedSections reflects only the latest return, not a union of past returns', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    // First return: flags contact_information only.
    $this->engine->returnForRevision($doc, $this->sdaoA, 'First round.', ['contact_information']);
    $doc->refresh();
    $this->engine->resubmit($doc, $this->studentAlpha);
    $doc->refresh();

    // Second return: flags a completely different section.
    $this->engine->returnForRevision($doc, $this->sdaoA, 'Second round.', ['adviser_selection']);
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedSections', ['adviser_selection'])
        );
});

test('edit page flaggedSections is empty when the return had no flagged sections', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision($doc, $this->sdaoA, 'General comment only, no sections flagged.');
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedSections', [])
        );
});
