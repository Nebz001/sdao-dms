<?php

namespace App\Policies;

use App\Approval\StepApproverResolver;
use App\Enums\DocumentStatus;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\DocumentStepApproval;
use App\Models\DocumentTransition;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Organizations\OrganizationMembershipService;

class DocumentPolicy
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly StepApproverResolver $approverResolver,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * Can the user submit a new document for this org?
     */
    public function submit(User $user, Organization $organization): bool
    {
        return $this->membershipService->activeMembershipFor($user, $organization) !== null;
    }

    /**
     * Can the user propose a brand-new organization (Phase 2 item 5's founding
     * flow)? No $organization argument — none exists yet at this point. Must
     * be Verified and not already tied to another org (item 4's one-org rule).
     */
    public function propose(User $user): bool
    {
        return $user->isVerifiedAccount()
            && ! $this->membershipService->hasActiveMembershipElsewhere($user);
    }

    /**
     * Can the user view this document? Either an officer who can act on it
     * (the document's own submitter — a founding student has no membership
     * yet on their own pending proposal, Phase 2 item 5 — or any active
     * officer of the document's own organization, i.e. the president or
     * secretary; see OrganizationMembershipService::canActOnDocument()),
     * an approver whose current step in this document's chain is active right
     * now (`review()`), an approver who has already legitimately acted on it
     * (`hasActedOn()`), or an approver for any step this document's chain has
     * already REACHED (`isChainApprover()`) — so a document doesn't vanish on
     * an approver the moment it moves past their step, and doesn't vanish on
     * a role holder who inherited the seat after their predecessor acted, or
     * who was provisioned mid-chain and so has no rows of their own. Once a
     * step is reached, its approver's read access persists for good —
     * through Returned, Approved, and Rejected alike.
     * Prevents any authenticated user from reading another organization's
     * document by guessing/enumerating IDs: an approver whose step this
     * document has never reached matches none of these, and role resolution
     * is scoped to the document's own organization throughout.
     */
    public function view(User $user, Document $document): bool
    {
        return $this->membershipService->canActOnDocument($user, $document)
            || $this->review($user, $document)
            || $this->hasActedOn($user, $document)
            || $this->isChainApprover($user, $document);
    }

    /**
     * Can the user view this document's review screen? Same as `view()`'s
     * approver-facing clauses, without the submitter/membership clauses that
     * don't apply to approver-only screens (Remediation-phase fix — see
     * `review()`'s docblock for the bug this closes), plus `viewArchive()`'s
     * blanket SDAO access to terminal documents. `isChainApprover()` is
     * checked last: it's the broadest and the most expensive clause, so the
     * cheap ones get a chance to short-circuit first.
     */
    public function reviewView(User $user, Document $document): bool
    {
        return $this->review($user, $document)
            || $this->hasActedOn($user, $document)
            || $this->viewArchive($user, $document)
            || $this->isChainApprover($user, $document);
    }

    /**
     * Can the user read this document as an archive record — i.e. it has
     * left the review queues entirely (Approved or Rejected, per
     * DocumentStatus::isTerminal()) and the reader is SDAO?
     *
     * Exists because `hasActedOn()` only incidentally covers SDAO for
     * approved documents (every SDAO step requires both members, so both
     * always have an acted-on row once a document is Approved). That
     * coincidence does NOT cover a document rejected before it ever reached
     * the SDAO step, nor a newly provisioned SDAO member with no historical
     * rows — both would otherwise 403 out of the admin document archive.
     *
     * Deliberately terminal-only: this grants nothing over an in-review or
     * returned document, so the "approver, but not their turn yet" 403
     * boundary pinned by DocumentViewAuthorizationTest is untouched.
     */
    public function viewArchive(User $user, Document $document): bool
    {
        return $document->status->isTerminal()
            && $this->roleDirectory->sdaoMembers()->contains('id', $user->id);
    }

    /**
     * Can the user manage this organization's officers — view the roster and
     * student picker, bind, or deactivate? The org's adviser only — the same
     * check BindOrganizationOfficer performs before binding, reused (not
     * duplicated) here for the view/bind/deactivate paths via
     * RoleDirectory::isAdviserOf().
     */
    public function manageOfficers(User $user, Organization $organization): bool
    {
        return $this->roleDirectory->isAdviserOf($user, $organization);
    }

    /**
     * Can the user decide (approve/decline) this organization's incoming
     * join requests? Broader than manageOfficers() — the task explicitly
     * scopes this to "an organization's existing officer or adviser", so an
     * active president/secretary can act here even though only the adviser
     * can manage officer turnover.
     */
    public function manageJoinRequests(User $user, Organization $organization): bool
    {
        return $this->roleDirectory->isAdviserOf($user, $organization)
            || $this->membershipService->activeOfficersFor($organization)->contains('id', $user->id);
    }

    /**
     * Can the user edit this document? Only when Returned, and only an
     * officer who can act on it — the original submitter or any active
     * officer (president/secretary — equal partners, per CLAUDE.md) of the
     * document's organization. See OrganizationMembershipService::canActOnDocument().
     */
    public function edit(User $user, Document $document): bool
    {
        return $document->status === DocumentStatus::Returned
            && $this->membershipService->canActOnDocument($user, $document);
    }

    /**
     * Can the user review (approve/reject/return) this document RIGHT NOW?
     *
     * Generalised to "actor ∈ current-step approvers" so that the long
     * proposal chains route correctly. For short chains (registration,
     * calendar) the current step is always the SDAO step, so the behavior
     * is identical to the old SDAO-membership check.
     *
     * Requires status = InReview (Remediation-phase fix): without this, a
     * Returned document — whose current_step_position still points at the
     * returning approver's step, invariant #2 — would pass this check and
     * let the action controllers reach ApprovalEngine::guardStatus(), which
     * throws an unhandled RuntimeException (500) instead of a clean deny.
     *
     * Note this is deliberately narrower than "may view" — a document is no
     * longer actionable by an approver once it moves off their step, even
     * though they should still be able to read it (see `hasActedOn()` /
     * `reviewView()` / `view()`).
     */
    public function review(User $user, Document $document): bool
    {
        if ($document->status !== DocumentStatus::InReview) {
            return false;
        }

        if ($document->workflow_template_id === null || $document->current_step_position === null) {
            return false;
        }

        $step = WorkflowStep::query()
            ->where('workflow_template_id', $document->workflow_template_id)
            ->where('position', $document->current_step_position)
            ->first();

        if ($step === null) {
            return false;
        }

        try {
            return $this->approverResolver->approversFor($step, $document)->contains('id', $user->id);
        } catch (\Throwable) {
            // A misconfigured chain (missing role holder) must deny, not 500
            // — same defensive pattern as the review-queue filters
            // (e.g. ActivityProposalReviewController::index()).
            return false;
        }
    }

    /**
     * Is this user resolvable as an approver for a step this document's
     * chain has actually REACHED — its current step, or any step it has
     * already passed through (per the highest step_position recorded in
     * document_transitions)? Deliberately excludes steps the document has
     * never reached yet.
     *
     * Used in two places:
     *
     * 1. Read access, via `view()`/`reviewView()`. "Was ever a step on this
     *    chain" is the right shape for reading: unlike `hasActedOn()`, it
     *    doesn't require the reader to have personally clicked, so it also
     *    covers a newly provisioned role holder and a seat reassigned after
     *    the predecessor acted. Resolution is live and organization-scoped,
     *    so another org's adviser or a future step's approver still matches
     *    nothing.
     *
     * 2. Stale-action messaging, via
     *    HandlesReviewActions::authorizeReviewAction() — deciding whether a
     *    no-longer-valid review action came from someone with a genuine,
     *    momentarily stale stake, or from a total stranger:
     *      - the OTHER SDAO member just finalized/rejected a dual-approval
     *        step while this member's page was still open → their step WAS
     *        reached (it's exactly where the document just was) → friendly
     *        redirect, not a raw 403.
     *      - a future-step approver (e.g. the dean, while the document still
     *        sits with the adviser) has a step that was never reached → real
     *        403.
     *
     * The reached-only scoping is what keeps both uses honest: it's the same
     * boundary DocumentViewAuthorizationTest pins ("approver, but not their
     * turn yet"), and it never widens past it — for reading OR for acting.
     */
    public function isChainApprover(User $user, Document $document): bool
    {
        if ($document->workflow_template_id === null) {
            return false;
        }

        $reachedPosition = DocumentTransition::query()
            ->where('document_id', $document->id)
            ->max('step_position');

        if ($reachedPosition === null) {
            return false;
        }

        $steps = WorkflowStep::query()
            ->where('workflow_template_id', $document->workflow_template_id)
            ->where('position', '<=', $reachedPosition)
            ->get();

        foreach ($steps as $step) {
            try {
                if ($this->approverResolver->approversFor($step, $document)->contains('id', $user->id)) {
                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    /**
     * Has this user ever legitimately acted on this document — approved,
     * rejected, or returned it? Both underlying rows are only ever written
     * by ApprovalEngine AFTER guardIsApprover() passes, so this can never
     * grant access to someone who wasn't a real approver at the time.
     */
    private function hasActedOn(User $user, Document $document): bool
    {
        return DocumentTransition::query()
            ->where('document_id', $document->id)
            ->where('actor_id', $user->id)
            ->exists()
            || DocumentStepApproval::query()
                ->where('document_id', $document->id)
                ->where('user_id', $user->id)
                ->exists();
    }
}
