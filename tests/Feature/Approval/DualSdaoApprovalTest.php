<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\DocumentStepApproval;
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
    $this->dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@sdao.test')->firstOrFail();

    // Set up a regular on-calendar doc already at the SDAO step (step 4).
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->adviser);
    $doc->refresh();
    $this->engine->approve($doc, $this->adviser);
    $this->engine->approve($doc, $this->chair);
    $this->engine->approve($doc, $this->dean);
    $doc->refresh();
    $this->doc = $doc;
    // Document is now at step 4 (SDAO), InReview.
});

// Test 15: first SDAO approval (1 of 2) does not advance; no next-step notification
test('first SDAO member approval does not advance and does not notify the next step', function () {
    $notificationsBefore = ApprovalNotification::where('document_id', $this->doc->id)->count();

    $this->engine->approve($this->doc, $this->sdaoA);
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::InReview);
    expect($this->doc->current_step_position)->toBe(4);

    // The next step (step 5, asst director) must NOT have been notified.
    $afterNotifications = ApprovalNotification::where('document_id', $this->doc->id)->count();
    expect($afterNotifications)->toBe($notificationsBefore); // no new notifications fired

    $nextStepNotified = ApprovalNotification::where('document_id', $this->doc->id)
        ->where('user_id', $this->asstDirector->id)
        ->exists();
    expect($nextStepNotified)->toBeFalse();
});

// Test 16: both SDAO approvals advance and notify next step
test('second SDAO member approval advances the document and notifies the next approver', function () {
    $this->engine->approve($this->doc, $this->sdaoA);
    $this->engine->approve($this->doc, $this->sdaoB);
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::InReview);
    expect($this->doc->current_step_position)->toBe(5);

    $nextStepNotified = ApprovalNotification::where('document_id', $this->doc->id)
        ->where('user_id', $this->asstDirector->id)
        ->where('step_position', 5)
        ->exists();
    expect($nextStepNotified)->toBeTrue();
});

// Test 17: split (one approves, one returns) → Returned; partial cleared; resume at SDAO step
test('SDAO split returns the document and clears the SDAO step partials', function () {
    $this->engine->approve($this->doc, $this->sdaoA);
    $this->engine->returnForRevision($this->doc, $this->sdaoB, 'Needs more detail');
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::Returned);
    // Resume position is the SDAO step (4).
    expect($this->doc->current_step_position)->toBe(4);

    // The partial approval from SDAO A must have been cleared.
    $sdaoStepApprovals = DocumentStepApproval::where('document_id', $this->doc->id)
        ->where('step_position', 4)
        ->count();
    expect($sdaoStepApprovals)->toBe(0);
});

// Test 18: after split + resubmit, both members must re-approve
test('after a split and resubmit both SDAO members must approve again', function () {
    $this->engine->approve($this->doc, $this->sdaoA);
    $this->engine->returnForRevision($this->doc, $this->sdaoB, 'Revision needed');
    $this->engine->resubmit($this->doc, $this->adviser);
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::InReview);
    expect($this->doc->current_step_position)->toBe(4);

    // Only one SDAO approval should not advance.
    $this->engine->approve($this->doc, $this->sdaoA);
    $this->doc->refresh();
    expect($this->doc->current_step_position)->toBe(4); // still at SDAO step

    // Both approve → advance.
    $this->engine->approve($this->doc, $this->sdaoB);
    $this->doc->refresh();
    expect($this->doc->current_step_position)->toBe(5);
});
