<?php

namespace App\Notifications;

use App\Enums\DocumentStatus;
use App\Mail\DocumentOutcomeMail;
use App\Models\Document;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to a document's officers (fallback: submitter) on the three outcomes
 * they cannot otherwise see (Approved, Rejected, Returned) — trigger
 * unchanged, still ApprovalEngine -> MailingSubmitterNotifier. See
 * ApproverHandOffNotification's docblock for the mail/database split and
 * why database queues sync while mail stays queued.
 */
class DocumentOutcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Document $document,
        public readonly DocumentStatus $outcome,
        public readonly ?string $comment = null,
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
        return (new DocumentOutcomeMail($notifiable, $this->document, $this->outcome, $this->comment))
            ->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = new DocumentOutcomeMail($notifiable, $this->document, $this->outcome, $this->comment);

        return [
            'kind' => 'document_outcome',
            'title' => "{$this->document->title} was {$mail->outcomeLabel()}",
            'body' => "{$this->document->form_type->label()} \u{2022} {$this->document->organization->name}",
            'url' => DocumentUrls::pathForSubmitter($this->document),
            'document_id' => $this->document->id,
            'form_type' => $this->document->form_type->value,
            'organization' => $this->document->organization->name,
            'status' => $this->outcome->value,
        ];
    }
}
