<?php

use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Coverage for `check:workflow-templates` (invariant #1 guard). Each test
 * seeds the correct 10-row baseline, then introduces exactly the one defect
 * class the command exists to catch.
 */
beforeEach(function () {
    $this->seed(WorkflowTemplateSeeder::class);
});

test('passes against a freshly seeded, correctly shaped set of 10 templates', function () {
    $this->artisan('check:workflow-templates')->assertSuccessful();
});

test('catches a duplicate null-variant row — the NULL-is-distinct unique index gap', function () {
    // Mirrors the real failure mode this command exists to catch: a second
    // row for the same (form_type, variant = NULL) pair, which the DB's
    // unique(['form_type', 'variant']) index does NOT prevent because
    // Postgres treats NULL as distinct in unique indexes.
    WorkflowTemplate::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'name' => 'Duplicate Organization Registration',
    ]);

    $this->artisan('check:workflow-templates')
        ->assertFailed()
        ->expectsOutputToContain('[DUPLICATE] organization_registration');
});

test('catches a missing row', function () {
    WorkflowTemplate::where('form_type', FormType::ActivityProposal)
        ->where('variant', ProposalVariant::ShsOffCalendar)
        ->firstOrFail()
        ->delete();

    $this->artisan('check:workflow-templates')
        ->assertFailed()
        ->expectsOutputToContain('[MISSING] activity_proposal / shs_off_calendar');
});

test('catches a mismatched step list', function () {
    $template = WorkflowTemplate::where('form_type', FormType::ActivityProposal)
        ->where('variant', ProposalVariant::RegularOnCalendar)
        ->firstOrFail();

    WorkflowStep::where('workflow_template_id', $template->id)
        ->where('role', Role::SdaoMember)
        ->update(['required_approvals' => 1]);

    $this->artisan('check:workflow-templates')
        ->assertFailed()
        ->expectsOutputToContain('[MISMATCH] activity_proposal / regular_on_calendar');
});
