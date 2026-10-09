<?php

namespace App\Notifications;

use App\Mail\AdviserAssignedMail;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * The ONE notice a new adviser gets when SDAO assigns them to an organization:
 * what is already waiting on them — documents sitting at their step and pending
 * join requests — in a single message, so nothing already in flight sits unseen
 * (invariant #9's hand-off only fires on the next transition, which for a
 * document already waiting at the adviser step may be weeks away). Not one
 * notice per document. Same mail + database split as ApproverHandOffNotification.
 */
class AdviserAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry attempts for a transient mail-provider failure (rate limits, timeouts). */
    public int $tries = 3;

    /** How many waiting documents the notice lists by title; the rest are only counted. */
    public const int LISTED_DOCUMENTS = 10;

    /**
     * @param  list<array{id: int, title: string, form_type: string}>  $waitingDocuments  at most LISTED_DOCUMENTS
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
        return (new AdviserAssignedMail(
            $this->organization,
            $this->waitingDocuments,
            $this->waitingDocumentCount,
            $this->pendingJoinRequestCount,
        ))->to($notifiable->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'adviser_assigned',
            'title' => "You are now the adviser of {$this->organization->name}",
            'body' => AdviserAssignedMail::waitingSentence($this->waitingDocumentCount, $this->pendingJoinRequestCount),
            // The queue the work is in: documents first, otherwise join requests.
            'url' => $this->waitingDocumentCount > 0
                ? route('review.activity-proposals.index', absolute: false)
                : route('review.join-requests.index', absolute: false),
            'document_id' => null,
            'form_type' => null,
            'organization' => $this->organization->name,
            'waiting_documents' => $this->waitingDocumentCount,
            'pending_join_requests' => $this->pendingJoinRequestCount,
            'status' => null,
        ];
    }
}
