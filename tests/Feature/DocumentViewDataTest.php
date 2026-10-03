<?php

use App\Approval\ApprovalEngine;
use App\Enums\FormType;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * The shared document page's non-form-specific data (App\Approval\DocumentViewData),
 * read through a review page: header facts, approval flow, waiting banner,
 * unified history and the record card.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
});

test('a document in review exposes its flow, the waiting step and the short type label', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, app(ApprovalEngine::class), $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('view.typeLabel', 'Registration')
            ->where('view.subject', 'Computing Society')
            // Submitted, the SDAO step, Completed.
            ->has('view.flow', 3)
            ->where('view.flow.0.state', 'done')
            ->where('view.flow.1.name', 'SDAO review')
            ->where('view.flow.1.state', 'current')
            ->where('view.flow.1.isYou', true)
            ->where('view.flow.2.state', 'upcoming')
            ->where('view.waiting.step', 2)
            ->where('view.waiting.totalSteps', 3)
            ->where('view.waiting.stepName', 'SDAO review')
            ->where('view.quorum.required', 2)
            ->where('view.record.revisions', 0)
            ->where('view.record.decidedBy', null)
            ->where('view.chips.0.label', fn ($label) => is_string($label))
        );
});

test('history is newest first, and a student event carries the officer position as the role', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, app(ApprovalEngine::class), $this->studentAlpha);
    app(ApprovalEngine::class)->returnForRevision($doc->fresh(), $this->sdaoA, 'Fix the contact details.', ['contact_information']);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->has('view.history', 2)
            ->where('view.history.0.action', 'returned')
            ->where('view.history.0.actor.role', 'SDAO Member')
            ->where('view.history.0.flagged', ['Contact Information'])
            ->where('view.history.1.action', 'submitted')
            ->where('view.waiting', null)
        );
});
