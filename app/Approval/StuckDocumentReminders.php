<?php

namespace App\Approval;

use App\Approval\Exceptions\ReminderNotAllowedException;
use App\Dashboard\InReviewSnapshot;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\DocumentStepApproval;
use App\Models\User;
use App\Notifications\StuckDocumentReminderNotification;
use App\Organizations\OrganizationMembershipService;
use App\Support\DisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * The "Remind" button on the Stuck Documents page.
 *
 * Who gets it:
 * - In review: the approver(s) the document is waiting on. For the SDAO step
 *   that is the SDAO members who have not approved it yet.
 * - Returned: the organization's active officers (president and secretary);
 *   the original submitter when the organization has none, the same fallback
 *   ApprovalEngine uses for outcome notifications.
 *
 * At most one reminder per document every COOLDOWN_HOURS, counted from the
 * newest row in document_reminders.
 */
class StuckDocumentReminders
{
    public const int COOLDOWN_HOURS = 24;

    public function __construct(
        private readonly StepApproverResolver $resolver,
        private readonly OrganizationMembershipService $membershipService,
    ) {}

    /** Whether the document is waiting on an approver (true) or on the organization (false). */
    public function isWaitingOnApprover(Document $document): bool
    {
        return $document->status === DocumentStatus::InReview;
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(Document $document): Collection
    {
        $document->loadMissing('organization', 'workflowTemplate.steps', 'submitter');

        if ($document->status === DocumentStatus::Returned) {
            $officers = $this->membershipService->activeOfficersFor($document->organization);

            return $officers->isNotEmpty()
                ? $officers->values()
                : collect([$document->submitter])->filter()->values();
        }

        if ($document->status !== DocumentStatus::InReview) {
            return collect();
        }

        $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);

        if ($step === null) {
            return collect();
        }

        try {
            $approvers = $this->resolver->approversFor($step, $document)->filter();
        } catch (Throwable) {
            return collect();
        }

        $alreadyApproved = DocumentStepApproval::query()
            ->where('document_id', $document->id)
            ->where('workflow_step_id', $step->id)
            ->pluck('user_id');

        return $approvers->reject(fn (User $user) => $alreadyApproved->contains($user->id))->values();
    }

    /**
     * When the next reminder may be sent, or null when one can go now.
     */
    public function nextAvailableAt(Document $document): ?CarbonInterface
    {
        return $this->nextAvailableForMany([$document->id])[$document->id] ?? null;
    }

    /**
     * Next-available time for each document id that is on cooldown (ids that
     * can be reminded now are left out). One query for the whole list.
     *
     * @param  array<int, int>  $documentIds
     * @return array<int, CarbonInterface>
     */
    public function nextAvailableForMany(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $latest = DocumentReminder::query()
            ->whereIn('document_id', $documentIds)
            ->get(['document_id', 'created_at'])
            ->groupBy('document_id')
            ->map(fn (Collection $rows) => $rows->max('created_at'));

        $available = [];

        foreach ($latest as $documentId => $sentAt) {
            $next = $sentAt->copy()->addHours(self::COOLDOWN_HOURS);

            if ($next->isFuture()) {
                $available[(int) $documentId] = $next;
            }
        }

        return $available;
    }

    /**
     * Sends the reminder and records it.
     *
     * @return Collection<int, User> Who it was sent to.
     *
     * @throws ReminderNotAllowedException
     */
    public function send(User $actor, Document $document): Collection
    {
        // Defence in depth: the route is already behind `can:access-admin`.
        Gate::forUser($actor)->authorize('access-admin');

        return DB::transaction(function () use ($actor, $document) {
            // Serialise two admins clicking Remind at once, as the engine does for approvals.
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [DocumentStatus::InReview, DocumentStatus::Returned], true)) {
                throw new ReminderNotAllowedException('This document is no longer open, so there is no one to remind.');
            }

            $next = $this->nextAvailableAt($locked);

            if ($next !== null) {
                throw new ReminderNotAllowedException('A reminder was already sent in the last 24 hours. You can send another after '.$next->setTimezone(DisplayTimezone::ASIA_MANILA)->format('n/j/Y g:i A').'.');
            }

            $recipients = $this->recipients($locked);

            if ($recipients->isEmpty()) {
                throw new ReminderNotAllowedException('No one holds this step yet, so there is no one to remind.');
            }

            $locked->load('latestTransition');
            $idleDays = InReviewSnapshot::idleDays($locked);
            $toApprover = $this->isWaitingOnApprover($locked);

            foreach ($recipients as $recipient) {
                $recipient->notify(new StuckDocumentReminderNotification($locked, $toApprover, $idleDays));
            }

            DocumentReminder::create([
                'document_id' => $locked->id,
                'sent_by' => $actor->id,
                'recipient_count' => $recipients->count(),
                'created_at' => now(),
            ]);

            return $recipients;
        });
    }
}
