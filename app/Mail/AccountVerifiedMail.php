<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued to a self-registered student the moment SDAO marks their account
 * Verified via the Pending Accounts queue (VerifyAccount::execute). Dispatched
 * via ->queue() so a mail-provider failure never blocks the verification
 * itself; $tries/backoff() retry a transient failure before giving up to
 * failed_jobs.
 */
class AccountVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    public function __construct(public readonly User $account) {}

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
        return new Envelope(
            subject: 'Your SDAO account has been verified',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-verified',
            with: [
                'accountName' => $this->account->name,
                'loginUrl' => route('login'),
            ],
        );
    }
}
