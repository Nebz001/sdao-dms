<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\ApproverNotifier;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\User;

/**
 * Development stub for the approver notifier (invariant #9).
 *
 * Records a row in approval_notifications so the engine trigger is testable.
 * The SSO slice swaps this out for a real email implementation as a localized
 * container rebind — identical to how DevIdentityProvider works in Slice 0.
 */
class RecordingApproverNotifier implements ApproverNotifier
{
    public function notify(User $approver, Document $document, int $stepPosition): void
    {
        ApprovalNotification::create([
            'document_id' => $document->id,
            'user_id' => $approver->id,
            'step_position' => $stepPosition,
            'created_at' => now(),
        ]);
    }
}
