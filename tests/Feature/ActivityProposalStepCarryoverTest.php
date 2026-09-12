<?php

use App\ActivityProposals\StartProposalDraft;
use App\Enums\ProposalCalendarMode;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Group D item 5 — step 1 → step 2 carryover.
 * ActivityProposalController::continue() was missing activity_nature_label,
 * activity_type_label, partner_organizations, and target_sdg_labels from its
 * proposal prop, so a student writing step 2 couldn't see what they picked
 * at step 1. Fixed by exposing the same four accessors show()/edit()/the
 * review controller already expose.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->startDraft = app(StartProposalDraft::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->student = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

test('the continue page exposes the step-1 nature, type, partner orgs, and SDGs chosen at step 1', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Carryover Test Activity',
            'venue' => 'Room 500',
            'activity_date' => '2026-11-15',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
            'activity_nature' => 'others',
            'activity_nature_other' => 'Cosplay meetup',
            'activity_type' => 'others',
            'activity_type_other' => 'Photo walk',
            'partner_organizations' => ['Partner Org A', 'Partner Org B'],
            'target_sdg' => ['quality_education', 'life_on_land'],
            'proposed_budget' => '5000.00',
            'budget_source' => 'rso_fund',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.activity_nature_label', 'Others — Cosplay meetup')
            ->where('proposal.activity_type_label', 'Others — Photo walk')
            ->where('proposal.partner_organizations', ['Partner Org A', 'Partner Org B'])
            ->where('proposal.target_sdg_labels', fn ($labels) => $labels->count() === 2
                && $labels->contains('Quality Education')
                && $labels->contains('Life on Land'))
            ->where('proposal.budget_source_label', 'RSO Fund')
        );
});

test('a normal (non-Others) nature and type carry over as their plain labels, with no free-text suffix', function () {
    $document = $this->startDraft->execute(
        actor: $this->student,
        organization: $this->org,
        mode: ProposalCalendarMode::OffCalendar,
        data: [
            'title' => 'Carryover Plain Labels Test',
            'venue' => 'Room 501',
            'activity_date' => '2026-11-16',
            'start_time' => '09:00',
            'end_time' => '11:00',
            'term' => 'first_term',
            'activity_nature' => 'co_curricular',
            'activity_type' => 'seminar_workshop',
            'partner_organizations' => ['Solo Partner'],
            'target_sdg' => ['quality_education'],
            'proposed_budget' => '3000.00',
            'budget_source' => 'external',
        ],
        attachmentFiles: proposalStepOneAttachmentFiles(),
    );

    $this->actingAs($this->student)
        ->withoutVite()
        ->get(route('activity-proposals.continue', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-proposals/step-two')
            ->where('proposal.activity_nature_label', 'Co-Curricular')
            ->where('proposal.activity_type_label', 'Seminar/Workshop')
            ->where('proposal.partner_organizations', ['Solo Partner'])
            ->where('proposal.target_sdg_labels', fn ($labels) => count($labels) === 1 && str_contains($labels[0], 'Quality Education'))
            ->where('proposal.budget_source_label', 'External')
        );
});
