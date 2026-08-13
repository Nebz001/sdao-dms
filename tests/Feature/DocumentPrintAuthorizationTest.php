<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Authorization for the generic print route (documents.print — Phase 2,
 * printable official forms). Mirrors the view()/reviewView() boundaries
 * already pinned by DocumentViewAuthorizationTest, since the print
 * controller composes exactly those two abilities.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->computingSociety = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->itGuild = Organization::where('name', 'IT Guild')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->studentBeta = User::where('email', 'student-beta@students.nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->chairCs = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
});

function printAuthRegistration(Organization $org, User $submitter): Document
{
    $doc = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $submitter->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);

    return $doc;
}

test('the submitting org officer can print', function () {
    $doc = printAuthRegistration($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->actingAs($this->studentAlpha)
        ->get(route('documents.print', $doc))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('an officer of a different organization cannot print', function () {
    $doc = printAuthRegistration($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->actingAs($this->studentBeta)
        ->get(route('documents.print', $doc))
        ->assertForbidden();
});

test('a guest is redirected to login', function () {
    $doc = printAuthRegistration($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->get(route('documents.print', $doc))->assertRedirect(route('login'));
});

test('the current-step SDAO approver can print', function () {
    $doc = printAuthRegistration($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $this->actingAs($this->sdaoA)
        ->get(route('documents.print', $doc))
        ->assertOk();
});

test('an approver whose step has not been reached yet cannot print', function () {
    $doc = printAuthRegistration($this->computingSociety, $this->studentAlpha);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    // Registration/Renewal is a single SDAO-only step (invariant #1's short
    // chain); the program chair role is never part of this chain at all, so
    // it's a clean "never reached" approver, same boundary as
    // DocumentViewAuthorizationTest's "approver, but not their turn yet".
    $this->actingAs($this->chairCs)
        ->get(route('documents.print', $doc))
        ->assertForbidden();
});

test('an Activity Calendar document with no backing ActivityCalendar row 404s rather than 500ing', function () {
    // Every form type is supported as of Part 2, so PrintableForms::for()
    // returning null is no longer reachable via any FormType case — the
    // abort_unless(...404) branch it guards stays in place regardless.
    // Document::factory() alone creates no child ActivityCalendar row, which
    // exercises ActivityCalendarForm's own null-guard instead (mirrors "a
    // registration with no detail row" below).
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityCalendar,
        'organization_id' => $this->computingSociety->id,
        'status' => DocumentStatus::Approved,
        'submitted_by' => $this->studentAlpha->id,
    ]);

    $this->actingAs($this->studentAlpha)
        ->get(route('documents.print', $doc))
        ->assertNotFound();
});

test('a registration with no detail row 404s rather than 500ing', function () {
    // SubmitOrganizationRegistration/Renewal always create the Document and
    // its OrganizationRegistrationDetail in one transaction — there is no
    // reachable draft state without a detail row. This document is not
    // reachable through the app; it exists only to prove
    // OrganizationApplicationForm::data() degrades gracefully rather than
    // fataling on the unguarded `$detail->contact_person` etc. it used to do.
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->computingSociety->id,
        'status' => DocumentStatus::Draft,
        'submitted_by' => $this->studentAlpha->id,
    ]);

    $this->actingAs($this->studentAlpha)
        ->get(route('documents.print', $doc))
        ->assertNotFound();
});
