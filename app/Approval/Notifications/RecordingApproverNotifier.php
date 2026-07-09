<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\ApproverNotifier;
use App\Mail\ApproverHandOffMail;
use App\Models\ApprovalNotification;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Records a row in approval_notifications so the engine trigger is testable,
 * AND sends the approver a real, synchronous email (Phase 2 item 3 — the
 * actual delivery channel for invariant #9; the trigger itself is unchanged
 * from Slice 1, still fired once per approver from
 * ApprovalEngine::activateStep). Sent synchronously (not queued) so delivery
 * never silently depends on a queue worker running.
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

        Mail::to($approver)->send(new ApproverHandOffMail($approver, $document, $stepPosition));
    }
}
