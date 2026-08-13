<?php

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Printing\OrganizationApplicationForm;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Asserts on the rendered Blade HTML of print.organization-application
 * directly — not the compressed PDF bytes dompdf produces. This is the only
 * place in the suite that would have caught the ".bar td" vs "td.bar"
 * selector bug (every section header bar rendering completely unstyled):
 * DocumentPrintRenderTest only checks the output starts with "%PDF" and
 * exceeds a byte count, and OrganizationApplicationFormDataTest only checks
 * the data() array, never the markup it feeds into. This file closes that
 * gap for the pilot-fidelity fixes (Phase 2 remediation, part 1).
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

function renderedOrganizationApplicationHtml(FormType $formType = FormType::OrganizationRegistration): string
{
    $doc = Document::create([
        'form_type' => $formType,
        'variant' => null,
        'title' => 'Rendered HTML test',
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => Organization::where('name', 'Computing Society')->firstOrFail()->id,
        'workflow_template_id' => null,
        'submitted_by' => User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail()->id,
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

    $form = app(OrganizationApplicationForm::class);
    $doc->load($form->eagerLoads());

    return view($form->view(), $form->data($doc))->render();
}

test('every section header bar uses a selector that actually matches the markup', function () {
    $html = renderedOrganizationApplicationHtml();

    // The bug: layout.blade.php declared ".bar td" (a descendant selector)
    // while every section header is `<td class="bar">` — the class on the
    // cell itself, never matched by that selector. Assert the CSS rule the
    // markup can actually match, and that every section cell carries the
    // class.
    expect($html)->toContain('.bar {')
        ->not->toContain('.bar td {');

    foreach ([
        '1. Contact Information',
        '2. DETAILS OF ORGANIZATION',
        '3. ENDORSEMENTS',
        '4. Received by:',
        '5. APPROVAL',
        '6. Additional Remarks from SDAO / CRSO',
        '7. REQUIREMENTS ATTACHED:',
    ] as $label) {
        expect($html)->toContain('class="bar">'.$label);
    }
});

test('section header casing is literal per the paper form, not blanket-transformed', function () {
    $html = renderedOrganizationApplicationHtml();

    // Casing is deliberately mixed on the paper form (Title Case for §1, §4,
    // §6; ALL CAPS for §2, §3, §5, §7) — a blanket text-transform: uppercase
    // would destroy that. Assert the literal strings are present and that
    // the always-uppercase forms are NOT present as evidence no transform is
    // silently re-applying them.
    expect($html)
        ->toContain('1. Contact Information')
        ->not->toContain('1. CONTACT INFORMATION')
        ->toContain('4. Received by:')
        ->not->toContain('4. RECEIVED BY:')
        ->toContain('6. Additional Remarks from SDAO / CRSO')
        ->not->toContain('6. ADDITIONAL REMARKS FROM SDAO / CRSO');
});

test('the Application For / Academic Year row is four cells, not two', function () {
    $html = renderedOrganizationApplicationHtml();

    expect($html)
        ->toContain('class="label-col">APPLICATION FOR')
        ->toContain('class="label-col">Academic Year')
        ->not->toContain('<strong>Academic Year:</strong>');
});

test('College prints the school name only, without the program suffix', function () {
    $html = renderedOrganizationApplicationHtml();

    // Computing Society belongs to a program (Slice-seeded regular school
    // org) — the pre-fix markup appended " — {program}" to this field, which
    // has no counterpart in the paper form's single "College" field.
    expect($html)->not->toContain(' — ');
});
