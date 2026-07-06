<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->submitter = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
});

// Test 7: submit → InReview, step 1, template bound, notification to step-1 approvers
test('submitting a draft document sets status to InReview at step 1 and binds the template', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'variant' => null,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->submitter);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(1);
    expect($doc->workflow_template_id)->not->toBeNull();

    // Short chain step 1 is SDAO (both members) — both must be notified on submit.
    expect(ApprovalNotification::where('document_id', $doc->id)->count())->toBe(2);
});

// Test 8: submit binds the correct template per (form_type, variant)
test('submitting binds the template matching form type and variant', function () {
    $shsOrg = Organization::where('name', 'SHS Student Council')->firstOrFail();

    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::ShsOnCalendar,
        'organization_id' => $shsOrg->id,
        'status' => DocumentStatus::Draft,
    ]);

    $this->engine->submit($doc, $this->submitter);
    $doc->refresh();

    $template = $doc->workflowTemplate()->with('steps')->first();
    expect($template->variant)->toBe(ProposalVariant::ShsOnCalendar);
    // SHS on-calendar: adviser → principal → SDAO → 3 directors = 6 steps
    expect($template->steps)->toHaveCount(6);
    // Step 1 should be adviser; so adviser notification fires on submit
    expect($doc->current_step_position)->toBe(1);
    expect(ApprovalNotification::where('document_id', $doc->id)->count())->toBe(1);
});

// Test 9: cannot submit a document not in Draft
test('cannot submit a document that is already in review', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::InReview,
        'current_step_position' => 1,
    ]);

    expect(fn () => $this->engine->submit($doc, $this->submitter))->toThrow(InvalidTransitionException::class);
});

test('cannot submit an approved document', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Approved,
    ]);

    expect(fn () => $this->engine->submit($doc, $this->submitter))->toThrow(InvalidTransitionException::class);
});

test('cannot submit a rejected document', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Rejected,
    ]);

    expect(fn () => $this->engine->submit($doc, $this->submitter))->toThrow(InvalidTransitionException::class);
});
