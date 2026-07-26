<?php

use App\ActivityProposals\StartProposalDraft;
use App\ActivityProposals\SubmitActivityProposal;
use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;

/**
 * Remediation-phase regression suite for the "403 This action is
 * unauthorized" bug on Approve / Reject / Return.
 *
 * Root cause: DocumentPolicy::review() conflated "may act on this document
 * RIGHT NOW" with "may read this document" — and `view()` used it as its
 * only approver-facing clause. Any transition that moves current_step_position
 * off an approver's step (reject, final approve, or the chain simply
 * advancing) revoked that approver's read access to a document they had
 * just legitimately acted on. An earlier fix (commit 0df4129) patched only
 * the finalizing-approve redirect in all five controllers; reject and the
 * general "approver loses access after acting" case were never covered.
 *
 * The fix split the ability into `review()` (act now — unchanged, now also
 * requires status = InReview), `hasActedOn()`-backed `view()`/`reviewView()`
 * (read access persists for anyone with a real transition or step-approval
 * row on the document), and `isChainApprover()` (used only by the action
 * guard to distinguish "a real approver in this document's chain hit a
 * race" from "a total stranger" when producing the stale-action message).
 *
 * These tests reuse the existing global fixture helpers declared elsewhere
 * in tests/Feature (Pest loads every Feature file up front, so these are
 * already available): shortChainInReviewDoc() and
 * calendarInReviewDocWithActivities() from SectionFlagValidationTest.php,
 * submittedRenewal() from ReviewOrganizationRenewalTest.php,
 * submittedCalendar() from ReviewActivityCalendarTest.php,
 * submittedReportForComputingSociety() from ReviewAfterActivityReportTest.php,
 * and advanceToStep() from Approval/ReturnForRevisionTest.php.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->engine = app(ApprovalEngine::class);
    $this->startDraft = app(StartProposalDraft::class);
    $this->submitProposal = app(SubmitActivityProposal::class);

    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->studentAlpha = User::where('email', 'student-alpha@sdao.test')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@sdao.test')->firstOrFail();
    $this->chair = User::where('email', 'chair-cs@sdao.test')->firstOrFail();
    $this->dean = User::where('email', 'dean-ccit@sdao.test')->firstOrFail();
    $this->sdaoA = User::where('email', 'sdao-a@sdao.test')->firstOrFail();
    $this->sdaoB = User::where('email', 'sdao-b@sdao.test')->firstOrFail();
    $this->asstDirector = User::where('email', 'asst-director@sdao.test')->firstOrFail();
    $this->academicDirector = User::where('email', 'academic-director@sdao.test')->firstOrFail();
    $this->executiveDirector = User::where('email', 'executive-director@sdao.test')->firstOrFail();
    $this->outsider = User::factory()->create();
});

// ── 1. HTTP reject — previously untested for 4 of 5 document types ──────────
// Each also proves the actual bug: the rejecting approver must still be able
// to reopen the document afterward, not 403.

test('HTTP: SDAO reject terminates a renewal and the rejecting member can still view it', function () {
    $doc = submittedRenewal();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.renewals.reject', $doc), ['comment' => 'Not approved.'])
        ->assertRedirect(route('review.renewals.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.renewals.show', $doc))
        ->assertOk();
});

test('HTTP: SDAO reject terminates an activity calendar and the rejecting member can still view it', function () {
    $doc = submittedCalendar();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.activity-calendars.reject', $doc), ['comment' => 'Not approved.'])
        ->assertRedirect(route('review.activity-calendars.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertOk();
});

test('HTTP: SDAO reject terminates an after-activity report and the rejecting member can still view it', function () {
    $doc = submittedReportForComputingSociety();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.reports.reject', $doc), ['comment' => 'Not sufficient detail.'])
        ->assertRedirect(route('review.reports.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.reports.show', $doc))
        ->assertOk();
});

test('HTTP: adviser reject terminates a proposal at step 1 and the rejecting adviser can still view it', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);
    expect($doc->current_step_position)->toBe(1);

    $this->actingAs($this->adviser)->withoutVite()
        ->post(route('review.activity-proposals.reject', $doc), ['comment' => 'Not approved.'])
        ->assertRedirect(route('review.activity-proposals.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();
});

// QA test-plan gap (2.3): registration reject had HTTP coverage that the
// reject POST itself succeeds (OneOrgPerStudentTest.php), but nothing ever
// re-visited .show afterward — so nothing caught the "rejecting approver
// loses view access" half of the original bug for this specific form type.
test('HTTP: SDAO reject terminates a registration and the rejecting member can still view it', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.registrations.reject', $doc), ['comment' => 'Incomplete documentation.'])
        ->assertRedirect(route('review.registrations.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk();
});

// QA test-plan gap (5.2): the proposal reject test above only covers the
// on-calendar variant (step 1 = adviser). Off-calendar relocates SDAO to
// step 1 (invariant #8) — a different resolved role, never exercised by a
// reject test.
test('HTTP: SDAO reject terminates an off-calendar proposal at step 1 and the rejecting member can still view it', function () {
    $draft = startOffCalendarDraft($this->studentAlpha, $this->org);

    $doc = $this->submitProposal->execute(
        actor: $this->studentAlpha,
        document: $draft,
        objectives: 'Objectives',
        narrative: 'Narrative',
    )['document'];

    expect($doc->current_step_position)->toBe(1);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.activity-proposals.reject', $doc), ['comment' => 'Not approved.'])
        ->assertRedirect(route('review.activity-proposals.index'));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();
});

// ── 2. HTTP return, following the redirect — for all five document types ────
// Existing coverage (SectionFlagValidationTest) never follows the redirect.

test('HTTP: return sends a registration back for revision and the returning approver can still view it', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.registrations.return', $doc), ['comment' => 'Please pick a different adviser.'])
        ->assertRedirect(route('review.registrations.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.show', $doc))
        ->assertOk();
});

test('HTTP: return sends a renewal back for revision and the returning approver can still view it', function () {
    $doc = submittedRenewal();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.renewals.return', $doc), ['comment' => 'Please fix the contact info.'])
        ->assertRedirect(route('review.renewals.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.renewals.show', $doc))
        ->assertOk();
});

test('HTTP: return sends an activity calendar back for revision and the returning approver can still view it', function () {
    $doc = calendarInReviewDocWithActivities($this->org, $this->engine, $this->studentAlpha, 1);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.activity-calendars.return', $doc), ['comment' => 'Fix the venue.'])
        ->assertRedirect(route('review.activity-calendars.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-calendars.show', $doc))
        ->assertOk();
});

test('HTTP: return sends an after-activity report back for revision and the returning approver can still view it', function () {
    $doc = submittedReportForComputingSociety();

    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.reports.return', $doc), ['comment' => 'Add participant numbers.'])
        ->assertRedirect(route('review.reports.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.reports.show', $doc))
        ->assertOk();
});

test('HTTP: return sends a proposal back for revision at step 1 and the returning adviser can still view it', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);

    $this->actingAs($this->adviser)->withoutVite()
        ->post(route('review.activity-proposals.return', $doc), ['comment' => 'Fix the objectives.'])
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);

    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();
});

// ── 3. Approver retains access as the chain advances past their step ────────
// This is the symptom reported on the proposal chain specifically.

test('HTTP: an approver who approved a mid-chain step can still view the proposal after it advances past them', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);

    $this->actingAs($this->adviser)->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    $doc->refresh();
    expect($doc->current_step_position)->toBe(2);

    // Regression: the adviser is no longer the current-step approver, but
    // having acted on the document, must still be able to open it.
    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();

    // A never-touched future-step approver is correctly still forbidden.
    $this->actingAs($this->dean)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertForbidden();
});

// ── 4. Non-current-step approver who never acted still gets a real 403 ──────
// The IDOR boundary this fix must not loosen.

test('HTTP: a future-step approver who has never acted gets a genuine 403 rejecting a proposal at an earlier step', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);
    expect($doc->current_step_position)->toBe(1); // adviser's step; chair (step 2) has no claim yet

    $this->actingAs($this->chair)->withoutVite()
        ->post(route('review.activity-proposals.reject', $doc), ['comment' => 'n/a'])
        ->assertForbidden();

    expect($doc->refresh()->status)->toBe(DocumentStatus::InReview);
});

test('HTTP: a future-step approver who has never acted gets a genuine 403 returning a proposal at an earlier step', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);

    $this->actingAs($this->dean)->withoutVite()
        ->post(route('review.activity-proposals.return', $doc), ['comment' => 'n/a'])
        ->assertForbidden();

    expect($doc->refresh()->status)->toBe(DocumentStatus::InReview);
});

test('HTTP: a total stranger to the document gets a genuine 403 rejecting a registration', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    $this->actingAs($this->outsider)->withoutVite()
        ->post(route('review.registrations.reject', $doc), ['comment' => 'n/a'])
        ->assertForbidden();

    expect($doc->refresh()->status)->toBe(DocumentStatus::InReview);
});

// ── 5. Stale action on an already-finalized/rejected document ───────────────
// A real approver of this document's chain, acting after another approver
// already resolved it, must get the friendly flash — not a raw 403.

test('HTTP: the losing SDAO member gets a friendly redirect (not a 403) approving a registration another member just finalized', function () {
    $doc = shortChainInReviewDoc(FormType::OrganizationRegistration, $this->org, $this->engine, $this->studentAlpha);

    // sdaoB finalizes the (single-step, dual-approver) short chain first.
    $this->engine->reject($doc, $this->sdaoB, 'Rejected by the other member.');
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Rejected);

    // sdaoA's page was still open — their click lands after the fact.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.registrations.reject', $doc), ['comment' => 'Also rejecting.'])
        ->assertRedirect(route('review.registrations.index'));

    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.registrations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.message', 'That document was already finalized by another approver.')
        );

    // And confirm it wasn't silently re-processed.
    expect($doc->refresh()->status)->toBe(DocumentStatus::Rejected);
});

// ── 6. Acting on a Returned document gives clean feedback, not a 500 ────────
// review() now checks status = InReview, so a Returned document (position
// still held at the returning step, per invariant #2) cleanly denies instead
// of reaching ApprovalEngine::guardStatus()'s unhandled RuntimeException.

test('HTTP: re-returning an already-Returned proposal gives a friendly redirect, not a 500', function () {
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

    $this->engine->returnForRevision($doc, $this->dean, 'Please revise.');
    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Returned);

    // The dean's own tab, still open, submits Return again before the
    // student has resubmitted.
    $this->actingAs($this->dean)->withoutVite()
        ->post(route('review.activity-proposals.return', $doc), ['comment' => 'Duplicate submission.'])
        ->assertRedirect(route('review.activity-proposals.index'));

    $this->actingAs($this->dean)->withoutVite()
        ->get(route('review.activity-proposals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('flash.toast.message', 'That document was already finalized by another approver.')
        );

    expect($doc->refresh()->status)->toBe(DocumentStatus::Returned);
});

// ── 7. Proposal chain coverage at positions 1, 4 (dual SDAO), and 7 ─────────

test('HTTP: approve at position 1 (adviser) advances the proposal and preserves the adviser\'s access', function () {
    $doc = authSubmittedProposal($this->startDraft, $this->submitProposal, $this->studentAlpha, $this->org);
    expect($doc->current_step_position)->toBe(1);

    $this->actingAs($this->adviser)->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    expect($doc->refresh()->current_step_position)->toBe(2);

    $this->actingAs($this->adviser)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();
});

test('HTTP: dual SDAO approval at position 4 — first approve is partial, second advances, both retain access', function () {
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
    expect($doc->current_step_position)->toBe(4);

    // First member's approve is partial — stays at step 4, no 403.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    expect($doc->refresh()->current_step_position)->toBe(4);

    // Second member's approve completes the step and advances to position 5.
    $this->actingAs($this->sdaoB)->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.show', $doc));

    expect($doc->refresh()->current_step_position)->toBe(5);

    // Both members retain access, having acted, even though the step moved on.
    $this->actingAs($this->sdaoA)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))->assertOk();
    $this->actingAs($this->sdaoB)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))->assertOk();
});

test('HTTP: final approve at position 7 (executive director) completes the proposal and redirects to the queue, not a 403', function () {
    $doc = advanceToStep(7, $this->engine, $this->org, [
        'adviser' => $this->adviser,
        'chair' => $this->chair,
        'dean' => $this->dean,
        'sdaoA' => $this->sdaoA,
        'sdaoB' => $this->sdaoB,
        'asstDirector' => $this->asstDirector,
        'academicDirector' => $this->academicDirector,
        'executiveDirector' => $this->executiveDirector,
    ]);
    expect($doc->current_step_position)->toBe(7);

    $this->actingAs($this->executiveDirector)->withoutVite()
        ->post(route('review.activity-proposals.approve', $doc))
        ->assertRedirect(route('review.activity-proposals.index'));

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Approved);
    expect($doc->current_step_position)->toBeNull();

    // The final approver must still be able to reopen the completed proposal.
    $this->actingAs($this->executiveDirector)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertOk();

    // A total stranger to the document — never in its chain at all —
    // correctly still cannot. (Every real approver in this specific chain
    // necessarily acted somewhere along the way to reach step 7, so a real
    // approver isn't a useful negative fixture here; advanceToStep(7, ...)
    // approved steps 1–6 inclusive, including the chair.)
    $this->actingAs($this->outsider)->withoutVite()
        ->get(route('review.activity-proposals.show', $doc))
        ->assertForbidden();
});
