<?php

namespace App\Approval\Contracts;

use App\Enums\TransitionAction;
use App\Models\Document;
use App\Models\User;

/**
 * Contract for the approver hand-off notification trigger (invariant #9).
 *
 * The engine calls this on every step activation. The stub records a row;
 * the SSO slice will swap in real email delivery as a localized rebind.
 *
 * $triggerAction is always one of Submitted, Advanced, or Resubmitted — the
 * only three TransitionActions that ever activate a step (see
 * ApprovalEngine::activateStep()). It exists purely so implementations can
 * word the notification correctly: a Resubmitted hand-off reactivates the
 * SAME step and typically the SAME approver(s) who returned it, so it must
 * not read like a first-time assignment.
 */
interface ApproverNotifier
{
    public function notify(User $approver, Document $document, int $stepPosition, TransitionAction $triggerAction): void;
}
