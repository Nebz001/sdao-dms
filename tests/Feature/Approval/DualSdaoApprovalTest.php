<?php

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use App\Enums\Role;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();

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

// Regression test for the "document stuck after SDAO, Assistant Director
// never sees it" bug: a STALE duplicate global-role assignment (e.g. a
// leftover from combining IdentitySeeder with a real-roster seeder against
// the same database — confirmed to have actually happened) must not hide
// the document from the real Assistant Director's queue once the SDAO
// quorum completes and the document advances to their step.
test('a stale duplicate Assistant Director assignment does not hide the document from the real holder\'s queue', function () {
    $staleHolder = User::factory()->create();
    RoleAssignment::create(['user_id' => $staleHolder->id, 'role' => Role::AssistantDirectorAcademicServices]);

    // The real holder's row must still be the FIRST-assigned one, matching
    // production (RealRosterSeeder/IdentitySeeder always seed the real
    // account before this kind of stray duplicate could exist).
    expect(RoleAssignment::where('role', Role::AssistantDirectorAcademicServices->value)->count())->toBe(2);

    $this->engine->approve($this->doc, $this->sdaoA);
    $this->engine->approve($this->doc, $this->sdaoB);
    $this->doc->refresh();

    expect($this->doc->status)->toBe(DocumentStatus::InReview);
    expect($this->doc->current_step_position)->toBe(5);

    $this->actingAs($this->asstDirector)
        ->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/activity-proposals/index')
            ->has('queue', 1)
            ->where('queue.0.id', $this->doc->id)
        );
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

// ── HTTP: redirect target at the SDAO step (drives whether the review show
// page's React component remounts — see resources/js/components/confirm-dialog.tsx
// and PLAN.md "modal auto-dismiss"). SDAO is step 4 of 7 on this chain, so
// unlike the short-chain forms (registration/renewal/report/calendar, where
// SDAO is the sole and final step), quorum completion here only advances to
// step 5 — it never finalizes the whole chain. Both approvals redirect to
// `.show`, not just the first. A modal-dismissal fix that only worked because
// the second approver "got lucky" landing on a different page component would
// still be broken here. ──────────────────────────────────────────────────────

test('HTTP: first SDAO approval on a proposal redirects back to the review show page', function () {
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $this->doc))
        ->assertRedirect(route('review.activity-proposals.show', $this->doc));

    $this->doc->refresh();
    expect($this->doc->current_step_position)->toBe(4);
});

test('HTTP: quorum-completing SDAO approval on a proposal also redirects to the show page, not the queue', function () {
    $this->actingAs($this->sdaoA)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $this->doc));

    $this->actingAs($this->sdaoB)
        ->withoutVite()
        ->post(route('review.activity-proposals.approve', $this->doc))
        ->assertRedirect(route('review.activity-proposals.show', $this->doc));

    $this->doc->refresh();
    expect($this->doc->current_step_position)->toBe(5);
});
