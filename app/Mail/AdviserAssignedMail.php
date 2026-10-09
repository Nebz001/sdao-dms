<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Queued to an adviser the moment SDAO assigns them to an organization. One
 * mail listing everything already waiting on them (see
 * AdviserAssignedNotification).
 */
class AdviserAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    /**
     * @param  list<array{id: int, title: string, form_type: string}>  $waitingDocuments
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly array $waitingDocuments,
        public readonly int $waitingDocumentCount,
        public readonly int $pendingJoinRequestCount,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /** "3 documents and 1 pending join request are waiting for you." — shared with the bell copy. */
    public static function waitingSentence(int $documents, int $joinRequests): string
    {
        $parts = [];

        if ($documents > 0) {
            $parts[] = $documents.' '.Str::plural('document', $documents);
        }

        if ($joinRequests > 0) {
            $parts[] = $joinRequests.' pending join '.Str::plural('request', $joinRequests);
        }

        if ($parts === []) {
            return 'Nothing is waiting on you yet.';
        }

        return implode(' and ', $parts).' '.($documents + $joinRequests === 1 ? 'is' : 'are').' waiting for you.';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You are now the adviser of {$this->organization->name}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.adviser-assigned',
            with: [
                'organizationName' => $this->organization->name,
                'waitingSentence' => self::waitingSentence($this->waitingDocumentCount, $this->pendingJoinRequestCount),
                'waitingDocuments' => $this->waitingDocuments,
                'moreDocuments' => max(0, $this->waitingDocumentCount - count($this->waitingDocuments)),
                'pendingJoinRequestCount' => $this->pendingJoinRequestCount,
                'queueUrl' => $this->waitingDocumentCount > 0
                    ? route('review.activity-proposals.index')
                    : route('review.join-requests.index'),
                'joinRequestsUrl' => route('review.join-requests.index'),
            ],
        );
    }
}
