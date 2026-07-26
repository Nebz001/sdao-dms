<?php

namespace App\Http\Controllers\Concerns;

use App\Approval\Exceptions\DuplicateApprovalException;
use App\Approval\Exceptions\InvalidTransitionException;
use App\Approval\Exceptions\UnauthorizedApproverException;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Shared approve/reject/return guards for the five *ReviewController
 * classes.
 *
 * A document can legitimately go stale between an approver opening the
 * review screen and submitting an action — e.g. the other SDAO member
 * finalizes a short chain first (both are required), or another approver
 * resolves the same step first. That must never surface as a raw
 * 403/500 — CLAUDE.md's standing rule requires clear feedback on every
 * save/update/change action. `authorizeReviewAction()` turns "no longer
 * actionable, but you did have a legitimate claim to it" into a friendly
 * flash + redirect, while a genuine outsider (never an approver, never
 * acted on this document) still gets a real 403 via the `view` ability.
 * `runReviewAction()` is defense-in-depth for the same race at the engine
 * layer, in case the document changed between the authorization check and
 * the engine call.
 */
trait HandlesReviewActions
{
    /**
     * Authorize a review action. Returns null when the action may proceed.
     * Returns a "stale" redirect when the user is a genuine approver
     * somewhere in this document's own chain (per
     * `DocumentPolicy::isChainApprover()`) but it's no longer actionable by
     * them right now — e.g. the other SDAO member just finalized a short
     * chain while this member's page was still open. Throws a real 403 for
     * anyone with no legitimate stake in this document at all.
     */
    private function authorizeReviewAction(User $user, Document $document, string $queueRoute): ?RedirectResponse
    {
        if (Gate::forUser($user)->allows('review', $document)) {
            return null;
        }

        Gate::forUser($user)->authorize('isChainApprover', $document);

        return $this->staleReviewAction($queueRoute);
    }

    /**
     * Run an approve/reject/return engine call, converting the engine's own
     * re-check exceptions (InvalidTransitionException, UnauthorizedApproverException,
     * DuplicateApprovalException — all unhandled RuntimeExceptions that would
     * otherwise 500) into the same friendly stale-action redirect.
     */
    private function runReviewAction(callable $action, string $queueRoute): ?RedirectResponse
    {
        try {
            $action();

            return null;
        } catch (InvalidTransitionException|UnauthorizedApproverException|DuplicateApprovalException) {
            return $this->staleReviewAction($queueRoute);
        }
    }

    private function staleReviewAction(string $queueRoute): RedirectResponse
    {
        return redirect()->route($queueRoute)
            ->with('flash', ['message' => 'That document was already finalized by another approver.']);
    }
}
