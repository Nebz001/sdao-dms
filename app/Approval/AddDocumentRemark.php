<?php

namespace App\Approval;

use App\Models\Document;
use App\Models\DocumentRemark;
use App\Models\User;
use App\Notifications\DocumentRemarkNotification;
use App\Organizations\OrganizationMembershipService;
use App\Support\PersonName;
use Illuminate\Support\Facades\Log;

/**
 * Appends a remark to a document and tells the organization's officers.
 *
 * Authorization is the caller's job (`Gate::authorize('remark', $document)`);
 * this only writes. A remark moves nothing: no status change, no transition
 * row, no engine involvement.
 */
class AddDocumentRemark
{
    public function __construct(private readonly OrganizationMembershipService $membershipService) {}

    public function execute(Document $document, User $author, string $body): DocumentRemark
    {
        $remark = DocumentRemark::create([
            'document_id' => $document->id,
            'user_id' => $author->id,
            'body' => $body,
            'created_at' => now(),
        ]);

        $this->notifyOfficers($document, $author, $body);

        return $remark;
    }

    /**
     * Best effort, after the write: a mail-provider hiccup must never lose a
     * remark the approver already saw saved. Falls back to the submitter when
     * the organization has no active officers yet (a founding registration),
     * the same rule as ApprovalEngine::notifySubmitter().
     */
    private function notifyOfficers(Document $document, User $author, string $body): void
    {
        $recipients = $this->membershipService->activeOfficersFor($document->organization);

        if ($recipients->isEmpty() && $document->submitter !== null) {
            $recipients = collect([$document->submitter]);
        }

        $authorName = PersonName::join($author->first_name, $author->last_name) ?: $author->name;

        foreach ($recipients->reject(fn (User $user) => $user->id === $author->id) as $recipient) {
            try {
                $recipient->notify(new DocumentRemarkNotification($document, $authorName, $body));
            } catch (\Throwable $e) {
                Log::warning('Document remark notification failed', ['document_id' => $document->id, 'exception' => $e->getMessage()]);
            }
        }
    }
}
