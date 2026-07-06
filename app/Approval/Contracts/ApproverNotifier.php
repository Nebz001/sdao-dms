<?php

namespace App\Approval\Contracts;

use App\Models\Document;
use App\Models\User;

/**
 * Contract for the approver hand-off notification trigger (invariant #9).
 *
 * The engine calls this on every step activation. The stub records a row;
 * the SSO slice will swap in real email delivery as a localized rebind.
 */
interface ApproverNotifier
{
    public function notify(User $approver, Document $document, int $stepPosition): void;
}
