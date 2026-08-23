<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued via Mail::to()->queue(), same as the other Mailables in this app —
 * a synchronous ->send() here previously blocked the registration/email-change
 * request on the SMTP round trip and produced 504s in production. A worker
 * actively polling the queue picks this up in well under a second, so the
 * recipient reaching the code-entry screen first is not a practical race.
 * $tries/backoff() retry a transient mail-provider failure before giving up
 * to failed_jobs, matching AccountVerifiedMail/AccountRejectedMail.
 */
class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

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

    public function __construct(
        public readonly string $code,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SDAO verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.email-verification-code',
            with: [
                'code' => $this->code,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
