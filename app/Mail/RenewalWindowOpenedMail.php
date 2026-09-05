<?php

namespace App\Mail;

use App\Models\User;
use App\Support\AcademicPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to active officers of every organization whose renewal just came
 * due, the moment SDAO advances the current term to 3rd
 * (App\Renewals\OpenRenewalSeason) — the renewal-season equivalent of
 * JoinRequestReceivedMail's hand-off. Dispatched via ->queue() so a
 * mail-provider failure never blocks the term change itself; $tries/backoff()
 * retry a transient failure before giving up to failed_jobs.
 */
class RenewalWindowOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(
        public readonly User $recipient,
        public readonly AcademicPeriod $period,
    ) {}

    /**
     * Seconds to wait before each retry, spacing attempts past a transient
     * provider rate limit.
     *
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

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.renewal-window-opened',
            with: [
                'recipientName' => $this->recipient->name,
                'coveredYear' => $this->period->nextAcademicYear(),
                'renewUrl' => route('renewals.create'),
            ],
        );
    }

    /**
     * Also reused by RenewalWindowOpenedNotification::toArray() so the mail
     * subject and the bell's title can never say something different for the
     * same event.
     */
    public function subjectLine(): string
    {
        return "Organization renewal is now open for {$this->period->nextAcademicYear()}";
    }
}
