<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent via Mail::to()->send() (NOT ->queue(), unlike the other Mailables in
 * this app) because the recipient is sitting on the code-entry screen
 * waiting — a queued send would race the page render.
 */
class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

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
