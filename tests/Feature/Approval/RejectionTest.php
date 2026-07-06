<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\TransitionAction;
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
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();

    // A short-chain doc in review at step 1 (SDAO).
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $doc->refresh();
    $this->doc = $doc;
});

// Test 19: reject → Rejected (terminal), step null, transition recorded with comment
test('rejecting a document marks it Rejected with a null step and records the comment', function () {
    $this->engine->reject($this->doc, $this->sdaoA, 'Does not meet requirements.');
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::Rejected);
    expect($this->doc->current_step_position)->toBeNull();

    $transition = $this->doc->transitions()
        ->where('action', TransitionAction::Rejected)
        ->first();
    expect($transition)->not->toBeNull();
    expect($transition->comment)->toBe('Does not meet requirements.');
    expect($transition->to_status)->toBe(DocumentStatus::Rejected);
});

// Test 20: rejected document cannot be resubmitted or re-submitted
test('a rejected document cannot be resubmitted', function () {
    $this->engine->reject($this->doc, $this->sdaoA);
    $this->doc->refresh();

    expect(fn () => $this->engine->resubmit($this->doc))->toThrow(InvalidTransitionException::class);
});

test('a rejected document cannot be re-submitted as a new submission', function () {
    $this->engine->reject($this->doc, $this->sdaoA);
    $this->doc->refresh();

    expect(fn () => $this->engine->submit($this->doc))->toThrow(InvalidTransitionException::class);
});

// Test 21: reject by non-resolved user is refused
test('a user not assigned to the step cannot reject', function () {
    $positionBefore = $this->doc->current_step_position;

    // Adviser is not the SDAO approver for step 1 of the short chain.
    expect(fn () => $this->engine->reject($this->doc, $this->adviser))
        ->toThrow(UnauthorizedApproverException::class);

    expect($this->doc->refresh()->status)->toBe(DocumentStatus::InReview);
    expect($this->doc->current_step_position)->toBe($positionBefore);
});
