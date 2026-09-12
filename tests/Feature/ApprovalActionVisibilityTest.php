<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Regression suite for Group E item 1 — the review screen's `canAct` prop.
 *
 * Root cause: ActivityProposalReviewController::show() (and the four
 * short-chain review controllers) only ever exposed `hasApproved` — whether
 * the viewer holds an approval row at whatever step the document CURRENTLY
 * sits at. That stays meaningful only for a step whose quorum survives a
 * single approval (SDAO's required_approvals = 2): one SDAO approval leaves
 * current_step_position unchanged, so `hasApproved` keeps matching the same
 * step and correctly stays true. For every single-approval role in the
 * activity-proposal chain, one approve() call immediately advances
 * current_step_position — so `hasApproved`, re-evaluated against the NEW
 * step, flips back to false and the Approve button reappeared active for
 * someone DocumentPolicy::review() no longer authorizes to use it.
 *
 * The fix adds `canAct` (== Gate::allows('review', $document) — the same
 * ability the action endpoints already gate on) and uses it, not
 * `hasApproved` alone, to decide whether the action card renders at all.
 * These tests assert the Inertia props directly rather than only the HTTP
 * outcome of an approve/reject/return POST (already covered by
 * ReviewActionAuthorizationTest), since the bug was purely about what the
 * review screen displays to a viewer who can no longer act.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@nu-lipa.edu.ph')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@nu-lipa.edu.ph')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@nu-lipa.edu.ph')->firstOrFail();
    $this->academicDirector = User::where('email', 'academic-director@nu-lipa.edu.ph')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@nu-lipa.edu.ph')->firstOrFail();
});

test('a single-approval role sees the Approve action before acting, and it disappears once their approval advances the step', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);
    expect($doc->current_step_position)->toBe(1); // the adviser's step

    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );

    $this->engine->approve($doc, $this->adviser);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(2); // advanced to the chair

    // Regression: previously `hasApproved` alone was recomputed against the
    // NEW current step (where the adviser has no approval row) and flipped
    // back to false, leaving the Approve button visible and enabled for an
    // adviser DocumentPolicy::review() no longer authorizes to use it.
    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canAct', false));

    // The chair — now the current-step approver — correctly can act.
    $this->actingAs($this->chair)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );
});

test('SDAO dual-approval quorum keeps the Approve action visible-but-disabled after a partial approval, and hides it for both members once quorum is met', function () {
    $doc = advanceToStep(4, $this->engine, $this->org, [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ]);
    expect($doc->current_step_position)->toBe(4); // SDAO's step

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );

    // sdaoA's approval is partial — quorum not met, the step does not advance.
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(4);

    // sdaoA still sees the card (can act), just disabled via hasApproved —
    // this is the pre-existing behavior that must be preserved exactly.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', true)
        );

    // sdaoB hasn't approved yet — their card is fully active.
    $this->actingAs($this->sdaoB)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );

    // sdaoB's approval completes the quorum and advances the step.
    $this->engine->approve($doc, $this->sdaoB);
    $doc->refresh();
    expect($doc->current_step_position)->toBe(5); // the asst. director's step

    // Neither SDAO member is an approver at position 5, and the step can
    // never revert to them — both action cards now hide.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page->where('canAct', false));

    $this->actingAs($this->sdaoB)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertInertia(fn ($page) => $page->where('canAct', false));
});

test('a short-chain SDAO-only review screen (activity calendar) is unaffected by the canAct gate', function () {
    $doc = submittedCalendar();
    expect($doc->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );

    // First SDAO approval is partial — the chain's only step doesn't
    // advance, so canAct must stay true (identical to the old isInReview
    // gate) while hasApproved disables the button.
    $this->engine->approve($doc, $this->sdaoA);
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::InReview);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', true)
        );

    $this->actingAs($this->sdaoB)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertInertia(fn ($page) => $page
            ->where('canAct', true)
            ->where('hasApproved', false)
        );
});
