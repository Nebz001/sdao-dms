<?php

use App\Approval\ApprovalEngine;
use App\Approval\Exceptions\InvalidTransitionException;
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
    $this->academicDirector = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@sdao.test')->firstOrFail();
});

/** Set up a regular on-calendar doc that has been approved up to (but not including) the given step. */
function advanceToStep(int $step, ApprovalEngine $engine, Organization $org, array $users): Document
{
    $doc = Document::factory()->create([
        'form_type' => FormType::ActivityProposal,
        'variant' => ProposalVariant::RegularOnCalendar,
        'organization_id' => $org->id,
        'status' => DocumentStatus::Draft,
    ]);
    $engine->submit($doc);
    $doc->refresh();

    $approvers = [
        1 => $users['adviser'],
        2 => $users['chair'],
        3 => $users['dean'],
        // step 4 = SDAO (handled separately — both members needed)
        5 => $users['asstDirector'],
        6 => $users['academicDirector'],
        7 => $users['executiveDirector'],
    ];

    for ($i = 1; $i < $step; $i++) {
        $doc->refresh();
        if ($i === 4) {
            $engine->approve($doc, $users['sdaoA']);
            $doc->refresh();
            $engine->approve($doc, $users['sdaoB']);
        } elseif (isset($approvers[$i])) {
            $engine->approve($doc, $approvers[$i]);
        }
        $doc->refresh();
    }

    $doc->refresh();

    return $doc;
}

// Test 22: return at step R → Returned, pointer holds, lower approvals persist
test('returning at step 3 sets status to Returned and keeps step pointer at 3', function () {
    $doc = advanceToStep(3, $this->engine, $this->org, [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ]);

    $this->engine->returnForRevision($doc, $this->dean, 'Please revise Section 2.');
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Returned);
    expect($doc->current_step_position)->toBe(3); // holds at dean's step

    // Lower steps' approvals (adviser at 1, chair at 2) must be preserved.
    $lowerApprovals = DocumentStepApproval::where('document_id', $doc->id)
        ->where('step_position', '<', 3)
        ->count();
    expect($lowerApprovals)->toBe(2); // adviser + chair
});

// Test 23: resubmit from Returned re-enters at R (not step 1)
test('resubmit from Returned re-enters at the returning step, not step 1', function () {
    $users = [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ];
    $doc = advanceToStep(3, $this->engine, $this->org, $users);
    $this->engine->returnForRevision($doc, $this->dean, 'Revision needed.');

    $this->engine->resubmit($doc);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::InReview);
    expect($doc->current_step_position)->toBe(3); // resumes at dean, not step 1

    // Notification fired to the dean on re-entry.
    $deanNotifiedAtStep3 = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->dean->id)
        ->where('step_position', 3)
        ->count();
    expect($deanNotifiedAtStep3)->toBeGreaterThanOrEqual(1);
});

// Test 24: lower-ranked approvers are not re-consulted after resubmit
test('lower-ranked approvers are not re-notified after resubmit', function () {
    $users = [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ];
    $doc = advanceToStep(3, $this->engine, $this->org, $users);
    $this->engine->returnForRevision($doc, $this->dean, 'Revision.');

    // Capture notification count for adviser and chair before resubmit.
    $adviserBefore = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->adviser->id)->count();
    $chairBefore = ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->chair->id)->count();

    $this->engine->resubmit($doc);

    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->adviser->id)->count())->toBe($adviserBefore);
    expect(ApprovalNotification::where('document_id', $doc->id)
        ->where('user_id', $this->chair->id)->count())->toBe($chairBefore);
});

// Test 25: return clears only the returning step's partials, not lower ones
test('return clears only the current step partial approvals, not lower steps', function () {
    $doc = advanceToStep(3, $this->engine, $this->org, [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ]);

    // Steps 1 and 2 already have approvals (adviser + chair).
    $before = DocumentStepApproval::where('document_id', $doc->id)->count();
    expect($before)->toBe(2);

    $this->engine->returnForRevision($doc, $this->dean, 'Revision.');

    // Dean's step (3) had no partial (single approver, not yet started), so count unchanged.
    $after = DocumentStepApproval::where('document_id', $doc->id)->count();
    expect($after)->toBe(2); // lower approvals intact, step 3 had nothing to clear
});

// Test 26: adviser → chair → dean returns → resubmit → dean approves → chain continues
test('full return-resume scenario: adviser and chair are never re-consulted', function () {
    $users = [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ];
    $doc = advanceToStep(3, $this->engine, $this->org, $users);
    $this->engine->returnForRevision($doc, $this->dean, 'Needs work.');
    $this->engine->resubmit($doc);

    // Dean approves on resume → should advance to SDAO (step 4).
    $this->engine->approve($doc, $this->dean);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(4); // SDAO step

    // Continue through to completion without adviser or chair needing to act.
    $this->engine->approve($doc, $this->sdaoA);
    $this->engine->approve($doc, $this->sdaoB);
    $this->engine->approve($doc, $this->asstDirector);
    $this->engine->approve($doc, $this->academicDirector);
    $this->engine->approve($doc, $this->executiveDirector);
    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Approved);
});

// Test 27: cannot approve/reject/return a Draft or Returned document
test('cannot approve a Draft document', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    expect(fn () => $this->engine->approve($doc, $this->sdaoA))->toThrow(InvalidTransitionException::class);
});

test('cannot reject a Returned document', function () {
    $doc = advanceToStep(3, $this->engine, $this->org, [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ]);
    $this->engine->returnForRevision($doc, $this->dean);
    $doc->refresh();

    expect(fn () => $this->engine->reject($doc, $this->dean))->toThrow(InvalidTransitionException::class);
});

// Test 28: cannot resubmit a document that is not Returned
test('cannot resubmit a document that is not in Returned status', function () {
    $doc = Document::factory()->create([
        'form_type' => FormType::OrganizationRegistration,
        'organization_id' => $this->org->id,
        'status' => DocumentStatus::Draft,
    ]);
    expect(fn () => $this->engine->resubmit($doc))->toThrow(InvalidTransitionException::class);
});
