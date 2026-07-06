<?php

use App\Approval\ApprovalEngine;
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
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
});

// Test 31: submit/advance/resubmit each record notifications to every approver at the new step
test('submitting fires notifications to all step-1 approvers', function () {
    // Regular on-calendar: step 1 is adviser (single approver).
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $doc->refresh();

    expect(ApprovalNotification::where('document_id', $doc->id)->where('step_position', 1)->count())->toBe(1);
    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->adviser->id)->exists())->toBeTrue();
});

test('submitting a short-chain document fires notifications to both SDAO members', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $doc->refresh();

    // SDAO step requires both members — both must be notified.
    expect(ApprovalNotification::where('document_id', $doc->id)->where('step_position', 1)->count())->toBe(2);
    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->sdaoA->id)->exists())->toBeTrue();
    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->sdaoB->id)->exists())->toBeTrue();
});

test('advancing to step 2 fires a notification to the chair', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->current_step_position)->toBe(2);
    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->chair->id)->where('step_position', 2)->exists())->toBeTrue();
});

test('resubmit fires notifications to the resuming step approver', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $this->engine->returnForRevision($doc, $this->adviser, 'Missing signature.');
    $doc->refresh();

    $notifyCountBefore = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->adviser->id)->count();

    $this->engine->resubmit($doc);

    $notifyCountAfter = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->adviser->id)->count();
    expect($notifyCountAfter)->toBeGreaterThan($notifyCountBefore);
});

// Test 32: no notification on reject; no notification on non-quorum SDAO partial
test('rejecting a document does not fire any notification', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $countAfterSubmit = ApprovalNotification::where('document_id', $doc->id)->count();

    $this->engine->reject($doc, $this->sdaoA);
    $countAfterReject = ApprovalNotification::where('document_id', $doc->id)->count();

    expect($countAfterReject)->toBe($countAfterSubmit); // no new notifications
});

test('a non-quorum SDAO partial approval does not fire a notification to the next step', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc);
    $countAfterSubmit = ApprovalNotification::where('document_id', $doc->id)->count();

    // First approval (1 of 2 required) — should not advance.
    $this->engine->approve($doc, $this->sdaoA);
    $countAfterPartial = ApprovalNotification::where('document_id', $doc->id)->count();

    // No new notification must have fired (quorum not reached).
    expect($countAfterPartial)->toBe($countAfterSubmit);
});
