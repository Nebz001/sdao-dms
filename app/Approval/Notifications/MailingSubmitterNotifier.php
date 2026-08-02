<?php

namespace App\Approval\Notifications;

use App\Approval\Contracts\SubmitterNotifier;
use App\Enums\DocumentStatus;
use App\Mail\DocumentOutcomeMail;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the original submitter a real email on the three document outcomes
 * they cannot otherwise see (Approved, Rejected, Returned). Queued (not sent
 * inline) so a slow or rate-limited mail provider can never take down the
 * approval transition that triggered it; dispatch failures are caught and
 * logged rather than propagated, since notification delivery is best-effort
 * and must not block the underlying document transition — same defensive
 * shape as RecordingApproverNotifier.
 */
class MailingSubmitterNotifier implements SubmitterNotifier
{
    public function notify(User $submitter, Document $document, DocumentStatus $outcome, ?string $comment = null): void
    {
        try {
            Mail::to($submitter)->queue(new DocumentOutcomeMail($submitter, $document, $outcome, $comment));
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
