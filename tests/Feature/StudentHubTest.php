<?php

use App\Approval\CurrentApproverLabel;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Database\Seeders\WorkflowTemplateSeeder;
use Tests\Fixtures\MembershipSeeder;
use Tests\Fixtures\TestIdentitySeeder;

beforeEach(function () {
    $this->seed([TestIdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);

    $this->organization = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->officer = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

function hubDocument(Organization $organization, User $submitter, FormType $formType, DocumentStatus $status, ?int $step = null): Document
{
    $template = $step === null
        ? null
        : WorkflowTemplate::active()->where('form_type', $formType->value)->firstOrFail();

    return Document::create([
        'form_type' => $formType,
        'variant' => null,
        'title' => 'Hub fixture '.$formType->value,
        'status' => $status,
        'current_step_position' => $step,
        'organization_id' => $organization->id,
        'workflow_template_id' => $template?->id,
        'submitted_by' => $submitter->id,
    ]);
}

test('guests are sent to log in before any hub page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['hubs.submit', 'hubs.my-documents', 'hubs.review']);

test('each hub renders for a student and names the right hub', function (string $route, string $hub) {
    $this->actingAs($this->officer)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('hubs/show')
            ->where('hub', $hub)
            ->where('organizationName', 'Computing Society')
        );
})->with([
    ['hubs.submit', 'submit'],
    ['hubs.my-documents', 'my-documents'],
    ['hubs.review', 'review'],
]);

test('a student with no organization gets a hub with no organization name and no chips', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('hubs.submit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('organizationName', null)
            ->where('chips', [])
        );
});

test('the Submit hub only carries chips it can back with real data', function () {
    $this->actingAs($this->officer)
        ->get(route('hubs.submit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Nothing is awaiting a report and no draft exists yet, so neither chip is invented.
            ->missing('chips.report')
            ->missing('chips.draft')
            // The calendar chip always reflects whether this term's calendar was filed.
            ->where('chips.activity_calendar.tone', 'warning')
        );
});

test('the Submit hub shows a draft chip once a proposal draft exists', function () {
    hubDocument($this->organization, $this->officer, FormType::ActivityProposal, DocumentStatus::Draft);

    $this->actingAs($this->officer)
        ->get(route('hubs.submit'))
        ->assertInertia(fn ($page) => $page
            ->where('chips.draft.label', '1 draft saved')
            ->where('chips.draft.tone', 'neutral')
        );
});

test('the lists name the role a document is waiting on, and nothing for a finished one', function () {
    $inReview = hubDocument($this->organization, $this->officer, FormType::OrganizationRenewal, DocumentStatus::InReview, 1);

    expect(CurrentApproverLabel::for($inReview->load('workflowTemplate.steps')))->toBe('SDAO');

    $approved = hubDocument($this->organization, $this->officer, FormType::OrganizationRenewal, DocumentStatus::Approved);

    expect(CurrentApproverLabel::for($approved))->toBeNull();

    $this->actingAs($this->officer)
        ->get(route('renewals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('renewals.0.current_approver', fn ($value) => in_array($value, ['SDAO', null], true))
            ->has('canStart.enabled')
            ->has('canStart.reason')
        );
});

test('Document History counts in-review and returned documents for the filter tabs', function () {
    hubDocument($this->organization, $this->officer, FormType::OrganizationRenewal, DocumentStatus::InReview, 1);
    hubDocument($this->organization, $this->officer, FormType::ActivityCalendar, DocumentStatus::Returned, 1);
    hubDocument($this->organization, $this->officer, FormType::AfterActivityReport, DocumentStatus::Approved);

    $this->actingAs($this->officer)
        ->get(route('document-history.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 3)
            ->where('stats.inReview', 1)
            ->where('stats.returned', 1)
            ->where('stats.approved', 1)
            ->has('documents.data.0.currentApprover')
        );
});

test('Registrations tells an officer they cannot start a second one, with the reason', function () {
    $this->actingAs($this->officer)
        ->get(route('registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canStart.enabled', false)
            ->where('canStart.reason', 'Your organization is already registered.')
            ->has('stats.inReview')
            ->has('stats.returned')
        );
});

test('a student with no organization may start a registration', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('registrations.index'))
        ->assertInertia(fn ($page) => $page->where('canStart.enabled', true));
});
