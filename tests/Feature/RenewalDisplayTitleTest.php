<?php

use App\Approval\ApprovalEngine;
use App\Dashboard\DocumentDisplayTitle;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Term;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Renewals\SubmitOrganizationRenewal;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

/**
 * A renewal's stored title is "Organization Renewal — Org (2nd Term, 2026-2027)"
 * (kept for emails, notifications and page titles). Everywhere rows already
 * show the organization and form type as badges, the title is just
 * "Organization Renewal" and the term is its own field, never parsed back out
 * of the stored string.
 */
beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
});

function renewalWithTerm(Organization $org, DocumentStatus $status = DocumentStatus::Approved): Document
{
    $document = Document::factory()->create([
        'form_type' => FormType::OrganizationRenewal,
        'organization_id' => $org->id,
        'status' => $status,
        'title' => "Organization Renewal — {$org->name} (2nd Term, 2026-2027)",
    ]);

    OrganizationRegistrationDetail::factory()->create([
        'document_id' => $document->id,
        'academic_year' => '2026-2027',
        'term' => 'second_term',
        'covers_academic_year' => '2026-2027',
    ]);

    return $document->fresh();
}

/**
 * A real, submitted (InReview) renewal for Computing Society, filed in the 3rd
 * term of 2030-2031 against a registration approved earlier.
 */
function renewalInReviewForDisplay(Organization $org): Document
{
    $student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $engine = app(ApprovalEngine::class);

    $registration = Document::create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'title' => "Organization Registration — {$org->name}",
        'status' => DocumentStatus::Draft,
        'current_step_position' => null,
        'organization_id' => $org->id,
        'workflow_template_id' => null,
        'submitted_by' => $student->id,
    ]);
    OrganizationRegistrationDetail::create([
        'document_id' => $registration->id,
        'organization_type' => OrganizationType::CoCurricular->value,
        'purpose_of_organization' => 'Original description.',
        'contact_person' => 'Original Person',
        'contact_no' => '09171111111',
        'email_address' => 'original@example.test',
        'date_organized' => '2020-06-01',
        'adviser_id' => null,
    ]);
    $engine->submit($registration, $student);
    foreach (['sdao-a@nu-lipa.edu.ph', 'sdao-b@nu-lipa.edu.ph'] as $email) {
        $registration->refresh();
        $engine->approve($registration, User::where('email', $email)->firstOrFail());
    }

    CurrentPeriod::set(new AcademicPeriod('2030-2031', Term::ThirdTerm));

    return app(SubmitOrganizationRenewal::class)->execute(
        actor: $student,
        organization: $org,
        purposeOfOrganization: 'Renewed description.',
        contactPerson: 'Renewed Contact',
        contactNo: '09172222222',
        emailAddress: 'renewed@example.test',
        dateOrganized: '2020-06-01',
        attachmentFiles: renewalAttachmentFiles(),
    );
}

test('a renewal reads as "Organization Renewal" and carries its term separately', function () {
    $renewal = renewalWithTerm($this->org);

    expect(DocumentDisplayTitle::subject($renewal))->toBe('Organization Renewal')
        ->and(DocumentDisplayTitle::termLabel($renewal))->toBe('2nd Term, 2026-2027');
});

test('only a renewal has a term label', function () {
    $registration = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
    ]);
    OrganizationRegistrationDetail::factory()->create(['document_id' => $registration->id, 'academic_year' => '2026-2027', 'term' => 'second_term']);

    expect(DocumentDisplayTitle::termLabel($registration->fresh()))->toBeNull()
        ->and(DocumentDisplayTitle::subject($registration->fresh()))->toBe('Computing Society');
});

test('the renewal review queue rows carry the short title, the organization and the term', function () {
    $renewal = renewalInReviewForDisplay($this->org);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.renewals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('queue', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $renewal->id
                    && $row['title'] === 'Organization Renewal'
                    && $row['term'] === '3rd Term, 2030-2031'
                    && $row['organization']['name'] === 'Computing Society'
            ))
        );
});

test('the archive and the organization page show the short title with the term, never the stored string', function () {
    $renewal = renewalWithTerm($this->org);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('admin.archive.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('documents.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['id'] === $renewal->id
                    && $row['title'] === 'Organization Renewal'
                    && $row['term'] === '2nd Term, 2026-2027'
            ))
        );
});

test('the renewal review page header chips name the term', function () {
    $renewal = renewalInReviewForDisplay($this->org);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.renewals.show', $renewal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('document.title', 'Organization Renewal')
            ->where('detail.term_label', '3rd Term')
            ->where('view.chips', fn ($chips) => collect($chips)->contains(
                fn ($chip) => $chip['label'] === 'Term' && $chip['value'] === '3rd Term, 2030-2031'
            ))
        );
});
