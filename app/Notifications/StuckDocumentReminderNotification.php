<?php

namespace App\Notifications;

use App\Mail\StuckDocumentReminderMail;
use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * The one notification for a document waiting on someone, whoever starts it:
 * an SDAO admin's Remind button, the hourly scheduler's 24 hour reminder, the
 * overdue alert to the approver, and the single overdue alert to SDAO. The
 * `variant` picks the wording (see StuckDocumentReminderMail); everything else
 * is shared. Same two channels as ApproverHandOffNotification (mail queued,
 * bell row written synchronously), same single source of wording.
 */
class StuckDocumentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly Document $document,
        public readonly bool $toApprover,
        public readonly int $idleDays,
        public readonly string $variant = StuckDocumentReminderMail::VARIANT_REMINDER,
        public readonly int $overdueDays = 0,
        public readonly string $leadTimeLabel = '',
        public readonly ?string $waitingOn = null,
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
        return ['database', 'mail'];
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
        $overdue = $mail->isOverdueVariant();

        return [
            'kind' => $overdue ? 'step_overdue' : 'stuck_document_reminder',
            'title' => $mail->subjectLine(),
            'body' => match (true) {
                $mail->variant === StuckDocumentReminderMail::VARIANT_SDAO_OVERDUE => "{$this->document->form_type->label()} \u{2022} {$this->document->organization->name} \u{2022} {$mail->overdueText()} overdue",
                $overdue => "{$this->document->form_type->label()} \u{2022} {$this->document->organization->name} \u{2022} {$mail->overdueText()} overdue",
                $this->toApprover => "{$this->document->form_type->label()} \u{2022} {$this->document->organization->name} \u{2022} waiting {$mail->idleText()}",
                default => "Returned for revision \u{2022} waiting {$mail->idleText()}",
            },
            'url' => $this->toApprover
                ? DocumentUrls::pathForReviewer($this->document)
                : DocumentUrls::pathForSubmitter($this->document),
            'document_id' => $this->document->id,
            'form_type' => $this->document->form_type->value,
            'proposal_reference' => null,
            'organization' => $this->document->organization->name,
            'status' => null,
        ];
    }

    private function mailFor(User $recipient): StuckDocumentReminderMail
    {
        return new StuckDocumentReminderMail(
            $recipient,
            $this->document,
            $this->toApprover,
            $this->idleDays,
            $this->variant,
            $this->overdueDays,
            $this->leadTimeLabel,
            $this->waitingOn,
        );
    }

    private function recipient(object $notifiable): User
    {
        if (! $notifiable instanceof User) {
            throw new \InvalidArgumentException('Stuck document reminders require a User recipient.');
        }

        return $notifiable;
    }
}
