<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\DuplicateApprovalException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\TransitionAction;
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
    $this->dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->academicDirector = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@sdao.test')->firstOrFail();
});

/** Helper: create and submit a regular on-calendar proposal for Computing Society. */
function regularOnCalendarDoc(Organization $org, ApprovalEngine $engine, User $submitter): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $engine->submit($doc, $submitter);
    $doc->refresh();

    return $doc;
}

// Test 10: approve at a single-approver step advances one step and notifies next
test('approving the first single-approver step advances to step 2 and notifies next approver', function () {
    $doc = regularOnCalendarDoc($this->org, $this->engine, $this->adviser);
    // Step 1 = adviser; step 2 = program chair
    $notificationsBeforeAdvance = ApprovalNotification::where('document_id', $doc->id)->count();

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(2);
    // After advancing to step 2, a notification to the chair must have fired.
    $total = ApprovalNotification::where('document_id', $doc->id)->count();
    expect($total)->toBeGreaterThan($notificationsBeforeAdvance);
    $chairNotified = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->chair->id)
        ->where('step_position', 2)
        ->exists();
    expect($chairNotified)->toBeTrue();
});

// Test 11: approving the final step sets status to Approved
test('approving the final step marks the document as Approved (terminal)', function () {
    // Short chain = single SDAO step (required_approvals=2). Approve both.
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc, $this->sdaoA);
    $doc->refresh();

    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview); // quorum not yet reached

    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();

    $completed = $doc->transitions()->where('action', TransitionAction::Completed)->first();
    expect($completed)->not->toBeNull();
});

// Test 12: non-resolved user cannot approve
test('a user not assigned to the current step cannot approve', function () {
    $doc = regularOnCalendarDoc($this->org, $this->engine, $this->adviser);
    // Step 1 = adviser; try to approve with the dean
    $before = $doc->fresh();

    expect(fn () => $this->engine->approve($doc, $this->dean))
        ->toThrow(UnauthorizedApproverException::class);

    expect($doc->refresh()->current_step_position)->toBe($before->current_step_position);
});

// Test 13: same approver cannot approve twice
test('the same approver cannot approve the same step twice', function () {
    $doc = regularOnCalendarDoc($this->org, $this->engine, $this->adviser);
    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    // Now at step 2 (chair). Go back to adviser scenario using a fresh short-chain doc.
    $doc2 = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $this->engine->submit($doc2, $this->sdaoA);
    $doc2->refresh();

    $this->engine->approve($doc2, $this->sdaoA);
    $doc2->refresh();

    expect(fn () => $this->engine->approve($doc2, $this->sdaoA))
        ->toThrow(DuplicateApprovalException::class);
});

// Test 14: full regular on-calendar chain approved end-to-end
test('full regular on-calendar chain can be approved end-to-end', function () {
    $doc = regularOnCalendarDoc($this->org, $this->engine, $this->adviser);

    $this->engine->approve($doc, $this->adviser);     // step 1
    $this->engine->approve($doc, $this->chair);       // step 2
    $this->engine->approve($doc, $this->dean);        // step 3
    $this->engine->approve($doc, $this->sdaoA);       // step 4, approval 1/2
    $this->engine->approve($doc, $this->sdaoB);       // step 4, approval 2/2 → advance
    $this->engine->approve($doc, $this->asstDirector); // step 5
    $this->engine->approve($doc, $this->academicDirector); // step 6
    $this->engine->approve($doc, $this->executiveDirector); // step 7 (final)

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();

    // One hand-off transition per step advance + a Completed at the end.
    $advancedCount = $doc->transitions()->where('action', TransitionAction::Advanced)->count();
    expect($advancedCount)->toBe(6); // 7 steps = 6 advances then 1 Completed
    expect($doc->transitions()->where('action', TransitionAction::Completed)->count())->toBe(1);
});
