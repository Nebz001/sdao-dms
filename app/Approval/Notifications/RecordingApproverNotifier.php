<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\ApproverNotifier;
use App\Enums\TransitionAction;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\User;
use App\Notifications\ApproverHandOffNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Records a row in approval_notifications so the engine trigger is testable,
 * AND fires ApproverHandOffNotification — mail (queued) + database (sync,
 * the bell's row) off one class, the actual delivery channel for invariant
 * #9. The trigger itself is unchanged from Slice 1, still fired once per
 * approver from ApprovalEngine::activateStep. Dispatch failures are caught
 * and logged rather than propagated, since notification delivery is
 * best-effort and must not block the underlying document transition.
 */
class RecordingApproverNotifier implements ApproverNotifier
{
    public function notify(User $approver, Document $document, int $stepPosition, TransitionAction $triggerAction): void
    {
        ApprovalNotification::create([
            'document_id' => $document->id,
            'user_id' => $approver->id,
            'step_position' => $stepPosition,
            'created_at' => now(),
        ]);

        try {
            Notification::send($approver, new ApproverHandOffNotification($document, $stepPosition, $triggerAction));
        } catch (\Throwable $e) {
            Log::error('Approver hand-off notification failed to dispatch', [
                'document_id' => $document->id,
                'user_id' => $approver->id,
                'step_position' => $stepPosition,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
