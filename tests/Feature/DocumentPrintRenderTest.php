<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * The only two tests in this feature that touch real dompdf-rendered bytes
 * (Phase 2, printable official forms) — everything else about the printable
 * form's content lives in OrganizationApplicationFormDataTest, asserting on
 * the data() array. These two exist purely to prove the Blade template
 * actually renders end-to-end under the two conditions most likely to break
 * it: the real NU Lipa letterhead asset on disk (public/print/nu-lipa-logo.svg
 * — logoPath() prefers SVG over JPEG, see OrganizationApplicationForm) and a
 * maximum-length Purpose of Organization.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('the form renders a real PDF with the NU Lipa logo asset present', function () {
    $doc = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$this->org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'To advance the field.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    // logoPath() prefers the SVG over the JPEG fallback — only the SVG has
    // been supplied.
    expect(is_file(public_path('print/nu-lipa-logo.svg')))->toBeTrue();
    expect(is_file(public_path('print/nu-lipa-logo.jpg')))->toBeFalse();

    $response = $this->actingAs($this->studentAlpha)->get(route('documents.print', $doc));

    $response->assertOk();
    $content = $response->getContent();
    expect(str_starts_with($content, '%PDF'))->toBeTrue();
    expect(strlen($content))->toBeGreaterThan(1000);
});

test('a maximum-length purpose of organization still renders successfully', function () {
    $doc = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$this->org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => str_repeat('a', 5000), // StoreRegistrationRequest's max:5000
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $this->engine->submit($doc, $this->studentAlpha);
    $doc->refresh();

    $response = $this->actingAs($this->studentAlpha)->get(route('documents.print', $doc));

    $response->assertOk();
    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('every printable form uses the shared logo and footer note partials', function () {
    foreach (['organization-application', 'activity-proposal', 'activity-calendar', 'after-activity-report'] as $view) {
        $source = file_get_contents(resource_path("views/print/{$view}.blade.php"));

        expect($source)->toContain('<x-print.document-logo')
            ->and($source)->toContain('<x-print.document-footer-note')
            ->and($source)->not->toContain('>NU Lipa<');
    }
});

test('the shared header is the embedded PNG logo and the footer note is body-size italic', function () {
    expect(is_file(public_path('print/nulp-logo-light-bg.png')))->toBeTrue();

    $logo = (string) view('components.print.document-logo');
    $note = (string) view('components.print.document-footer-note');

    expect($logo)->toContain('src="data:image/png;base64,')->not->toContain('http');
    expect($note)->toContain('<strong>Note:</strong>')
        ->toContain('<em>This document does not require an electronic signature')
        ->toContain('font-size: inherit')
        ->toContain('border-top');
});

test('a printed registration embeds the logo image and sets the note in italics', function () {
    $doc = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$this->org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $this->org->id,
        'workflow_template_id' => null,
        'submitted_by' => $this->studentAlpha->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $doc->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'To advance the field.',
        'contact_person' => 'Contact Person',
        'contact_no' => '09170000000',
        'email_address' => 'contact@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);

    $pdf = $this->actingAs($this->studentAlpha)->get(route('documents.print', $doc))->assertOk()->getContent();

    expect(preg_match('/\/Subtype\s*\/Image/', $pdf))->toBe(1)
        ->and($pdf)->toContain('Helvetica-Oblique');
});
