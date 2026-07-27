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

/**
 * Bare document, no form-specific detail record — edit() controllers build
 * their `detail` prop null-safely (`$detail ? [...] : null`), so this is
 * sufficient for proving prop exposure without replicating the heavier
 * fixtures other test files use for renewal/report-specific business rules.
 */
function highlightTestSubmittedRenewal(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRenewal,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

function highlightTestSubmittedReport(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::AfterActivityReport,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $submitter->id,
    ]);
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

// --- Section-comments redesign: edit() also exposes the general comment and
// any per-section notes from the latest return, in context (see PLAN.md). --

test('edit page exposes flaggedComment and flaggedSectionComments from the latest return', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Fix these two things.',
        ['contact_information', 'attachments'],
        ['contact_information' => 'Phone number is missing.'],
    );
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedComment', 'Fix these two things.')
            ->where('flaggedSectionComments', ['contact_information' => 'Phone number is missing.'])
        );
});

test('edit page flaggedSectionComments is empty when no section-specific notes were given — the backward-compatible case every pre-existing return falls into', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    // No $sectionComments argument at all — exactly the shape of every
    // transition that predates this feature.
    $this->engine->returnForRevision($doc, $this->sdaoA, 'General comment only.', ['contact_information']);
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedComment', 'General comment only.')
            ->where('flaggedSectionComments', [])
        );
});

// --- Attachment-flagging-by-slot: a specific attachment slot's flag and
// comment surface on the edit page exactly like any other section — proving
// the full pipeline (registry → validation → persistence → prop exposure)
// for an attachment slot key specifically, across all 3 covered form types
// (see PLAN.md). --------------------------------------------------------------

test('edit page exposes a per-slot attachment flag and comment for Registration', function () {
    $doc = highlightTestSubmittedRegistration($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Please fix the flagged document.',
        ['by_laws'],
        ['by_laws' => 'This copy is outdated — please upload the current By-Laws.'],
    );
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('registrations.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('registrations/edit')
            ->where('flaggedSections', ['by_laws'])
            ->where('flaggedSectionComments', ['by_laws' => 'This copy is outdated — please upload the current By-Laws.'])
        );
});

test('edit page exposes a per-slot attachment flag and comment for Renewal', function () {
    $doc = highlightTestSubmittedRenewal($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Please fix the flagged document.',
        ['financial_statement'],
        ['financial_statement' => "Missing the treasurer's signature."],
    );
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('renewals.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('renewals/edit')
            ->where('flaggedSections', ['financial_statement'])
            ->where('flaggedSectionComments', ['financial_statement' => "Missing the treasurer's signature."])
        );
});

test('edit page exposes a per-slot attachment flag and comment for After-Activity Report', function () {
    $doc = highlightTestSubmittedReport($this->org, $this->engine, $this->studentAlpha);

    $this->engine->returnForRevision(
        $doc,
        $this->sdaoA,
        'Please fix the flagged document.',
        ['attendance_sheet'],
        ['attendance_sheet' => 'A few names are missing signatures.'],
    );
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->withoutVite()
        ->get(route('reports.edit', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/edit')
            ->where('flaggedSections', ['attendance_sheet'])
            ->where('flaggedSectionComments', ['attendance_sheet' => 'A few names are missing signatures.'])
        );
});
