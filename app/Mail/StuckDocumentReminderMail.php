<?php

namespace App\Mail;

use App\Models\Document;
use App\Models\User;
use App\Support\DocumentUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Every reminder about a document that is waiting on someone, in one
 * Mailable. Three variants:
 *
 * - `reminder`: a nudge. To the approver the document is waiting on (links to
 *   the review screen), or, when it was returned for revision, to the
 *   organization's officers (links to their own view).
 * - `overdue`: the same recipient, once the step has passed its lead time.
 *   Clearly different wording: it says the review window has passed.
 * - `sdao_overdue`: the one-off heads-up to SDAO that a step ran past its
 *   lead time, naming who it is waiting on.
 *
 * Reused by StuckDocumentReminderNotification::toArray() for the bell, so the
 * email subject and the in-app row always say the same thing.
 */
class StuckDocumentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public const string VARIANT_REMINDER = 'reminder';

    public const string VARIANT_OVERDUE = 'overdue';

    public const string VARIANT_SDAO_OVERDUE = 'sdao_overdue';

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly Document $document,
        public readonly bool $toApprover,
        public readonly int $idleDays,
        public readonly string $variant = self::VARIANT_REMINDER,
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

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function subjectLine(): string
    {
        return match ($this->variant) {
            self::VARIANT_OVERDUE => "Overdue: {$this->document->title} is past its review time",
            self::VARIANT_SDAO_OVERDUE => "Overdue: {$this->document->title} is waiting on {$this->waitingOn}",
            default => $this->toApprover
                ? "Reminder: {$this->document->title} is waiting for your review"
                : "Reminder: {$this->document->title} needs your changes",
        };
    }

    public function isOverdueVariant(): bool
    {
        return $this->variant !== self::VARIANT_REMINDER;
    }

    /** "5 days", "1 day", or "less than a day". */
    public function idleText(): string
    {
        return match (true) {
            $this->idleDays < 1 => 'less than a day',
            $this->idleDays === 1 => '1 day',
            default => "{$this->idleDays} days",
        };
    }

    /** "2 days", "1 day", or "less than a day": how far past the lead time. */
    public function overdueText(): string
    {
        return match (true) {
            $this->overdueDays < 1 => 'less than a day',
            $this->overdueDays === 1 => '1 day',
            default => "{$this->overdueDays} days",
        };
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.stuck-document-reminder',
            with: [
                'recipientName' => $this->recipient->name,
                'formTypeLabel' => $this->document->form_type->label(),
                'organizationName' => $this->document->organization->name,
                'documentTitle' => $this->document->title,
                'idleText' => $this->idleText(),
                'overdueText' => $this->overdueText(),
                'leadTimeLabel' => $this->leadTimeLabel,
                'waitingOn' => $this->waitingOn,
                'variant' => $this->variant,
                'toApprover' => $this->toApprover,
                'documentUrl' => $this->toApprover
                    ? DocumentUrls::forReviewer($this->document)
                    : DocumentUrls::forSubmitter($this->document),
            ],
        );
    }
}
