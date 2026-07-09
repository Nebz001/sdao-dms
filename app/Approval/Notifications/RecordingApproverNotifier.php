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
 * Phase 2 item 3 (wire real notification delivery) swaps this out for a real
 * email implementation as a localized container rebind.
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
