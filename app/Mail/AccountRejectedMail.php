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
 * Rejected via the Pending Accounts queue (RejectAccount::execute). Dispatched
 * via ->queue() so a mail-provider failure never blocks the rejection itself;
 * $tries/backoff() retry a transient failure before giving up to failed_jobs.
 */
class AccountRejectedMail extends Mailable
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
            subject: 'Your SDAO account application was not approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-rejected',
            with: [
                'accountName' => $this->account->name,
            ],
        );
    }
}
