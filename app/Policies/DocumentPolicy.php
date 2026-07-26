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
     * Can the user view this document? Either the document's own submitter
     * (a founding student has no membership yet on their own pending
     * proposal — Phase 2 item 5 — so this must be checked independently of
     * membership), an affiliated officer of the document's own organization,
     * an approver whose current step in this document's chain is active
     * right now (`review()`), or an approver who has already legitimately
     * acted on this document (`hasActedOn()`) — so a document doesn't vanish
     * on its own actor the moment it moves past their step (e.g. after they
     * reject it, finalize it, or the chain advances beyond them).
     * Prevents any authenticated user from reading another organization's
     * document by guessing/enumerating IDs: an approver who never acted and
     * whose step isn't active matches none of these.
     */
    public function view(User $user, Document $document): bool
    {
        return $document->submitted_by === $user->id
            || $this->membershipService->activeMembershipFor($user, $document->organization) !== null
            || $this->review($user, $document)
            || $this->hasActedOn($user, $document);
    }

    /**
     * Can the user view this document's review screen? Same as `view()`'s
     * approver-facing clauses, without the submitter/membership clauses that
     * don't apply to approver-only screens (Remediation-phase fix — see
     * `review()`'s docblock for the bug this closes).
     */
    public function reviewView(User $user, Document $document): bool
    {
        return $this->review($user, $document) || $this->hasActedOn($user, $document);
    }

    /**
     * Can the user manage (bind/deactivate) officers for this organization?
     * The org's adviser only — the same check BindOrganizationOfficer
     * performs before binding, reused (not duplicated) here for the
     * deactivation path via RoleDirectory::isAdviserOf().
     */
    public function manageOfficers(User $user, Organization $organization): bool
    {
        return $this->roleDirectory->isAdviserOf($user, $organization);
    }

    /**
     * Can the user edit this document? (Only when Returned, only the original submitter.)
     */
    public function edit(User $user, Document $document): bool
    {
        return $document->status === DocumentStatus::Returned
            && $document->submitted_by === $user->id;
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
     * Used only to decide, when a review action is no longer valid right
     * now, whether the user has a genuine — if momentarily stale — stake in
     * this document, versus a total stranger to it:
     *   - the OTHER SDAO member just finalized/rejected a dual-approval step
     *     while this member's page was still open → their step WAS reached
     *     (it's exactly where the document just was) → friendly redirect.
     *   - a future-step approver (e.g. the dean, while the document still
     *     sits with the adviser) has a step that was never reached → real
     *     403. This must stay a real 403: it's the same boundary
     *     DocumentViewAuthorizationTest pins for view() ("approver, but not
     *     their turn yet"), and reached-only scoping is what keeps this
     *     method from quietly widening past it.
     * See HandlesReviewActions::authorizeReviewAction().
     *
     * Deliberately NOT used by view()/reviewView() — this only softens
     * action error messaging, it never grants read access.
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
