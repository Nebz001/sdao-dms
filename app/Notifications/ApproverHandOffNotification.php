<?php

namespace App\Notifications;

use App\Enums\TransitionAction;
use App\Mail\ApproverHandOffMail;
use App\Models\Document;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Invariant #9's actual delivery — fired once per approver the moment
 * ApprovalEngine::activateStep hands them a document (trigger unchanged,
 * still ApprovalEngine -> RecordingApproverNotifier). Two channels off one
 * class, with no duplicated content: toMail() returns the existing
 * ApproverHandOffMail (so resources/views/mail/approver-hand-off.blade.php
 * and its subject/wording logic stay the single source of email content),
 * and toArray() reuses that same Mailable's subject() helper for the bell.
 *
 * viaConnections() splits queueing per channel: mail stays queued (matches
 * the ->queue() this replaces — a slow provider must never sit on the
 * request path), but database runs sync so the bell row exists the instant
 * this method returns, not whenever a queue worker next runs.
 */
class ApproverHandOffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Document $document,
        public readonly int $stepPosition,
        public readonly TransitionAction $triggerAction,
    ) {
        $this->document->loadMissing('organization');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => 'database'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return (new ApproverHandOffMail($notifiable, $this->document, $this->stepPosition, $this->triggerAction))
            ->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = new ApproverHandOffMail($notifiable, $this->document, $this->stepPosition, $this->triggerAction);

        return [
            'kind' => 'approver_hand_off',
            'title' => $mail->subjectLine(),
            'body' => "{$this->document->form_type->label()} \u{2022} {$this->document->organization->name}",
            'url' => DocumentUrls::pathForReviewer($this->document),
            'document_id' => $this->document->id,
            'form_type' => $this->document->form_type->value,
            'organization' => $this->document->organization->name,
            'status' => null,
        ];
    }
}
