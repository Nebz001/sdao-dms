<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\SubmitterNotifier;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentOutcomeNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Fires DocumentOutcomeNotification — mail (queued) + database (sync, the
 * bell's row) — to the original submitter on the three document outcomes
 * they cannot otherwise see (Approved, Rejected, Returned). Dispatch
 * failures are caught and logged rather than propagated, since notification
 * delivery is best-effort and must not block the underlying document
 * transition — same defensive shape as RecordingApproverNotifier.
 */
class MailingSubmitterNotifier implements SubmitterNotifier
{
    public function notify(User $submitter, Document $document, DocumentStatus $outcome, ?string $comment = null): void
    {
        try {
            Notification::send($submitter, new DocumentOutcomeNotification($document, $outcome, $comment));
        } catch (\Throwable $e) {
            Log::error('Submitter outcome notification failed to dispatch', [
                'document_id' => $document->id,
                'user_id' => $submitter->id,
                'outcome' => $outcome->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
