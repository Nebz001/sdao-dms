<?php

namespace App\Notifications;

use App\Mail\DocumentRemarkMail;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Fired to a document's officers (fallback: submitter) when an approver adds
 * a remark after approving. Same two channels as DocumentOutcomeNotification
 * (mail queued, bell row written synchronously); the wording says it is a
 * remark, never a status change.
 */
class DocumentRemarkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Document $document,
        public readonly string $authorName,
        public readonly string $body,
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
        $recipient = $this->recipient($notifiable);

        return $this->mailFor($recipient)->to($recipient->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $mail = $this->mailFor($this->recipient($notifiable));

        return [
            'kind' => 'document_remark',
            'title' => $mail->subjectLine(),
            'body' => "{$this->authorName} left a remark \u{2022} {$this->document->form_type->label()} \u{2022} {$this->document->organization->name}",
            'url' => DocumentUrls::pathForSubmitter($this->document),
            'document_id' => $this->document->id,
            'form_type' => $this->document->form_type->value,
            'proposal_reference' => null,
            'organization' => $this->document->organization->name,
            'status' => null,
        ];
    }

    private function mailFor(User $recipient): DocumentRemarkMail
    {
        return new DocumentRemarkMail($recipient, $this->document, $this->authorName, $this->body);
    }

    private function recipient(object $notifiable): User
    {
        if (! $notifiable instanceof User) {
            throw new \InvalidArgumentException('Document remarks require a User recipient.');
        }

        return $notifiable;
    }
}
