<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\ApproverNotifier;
use App\Mail\ApproverHandOffMail;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Records a row in approval_notifications so the engine trigger is testable,
 * AND queues the approver a real email (Phase 2 item 3 — the actual delivery
 * channel for invariant #9; the trigger itself is unchanged from Slice 1,
 * still fired once per approver from ApprovalEngine::activateStep). Queued
 * (not sent inline) so a slow or rate-limited mail provider can never take
 * down the approval transition that triggered it; dispatch failures are
 * caught and logged rather than propagated, since notification delivery is
 * best-effort and must not block the underlying document transition.
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

        try {
            Mail::to($approver)->queue(new ApproverHandOffMail($approver, $document, $stepPosition));
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
