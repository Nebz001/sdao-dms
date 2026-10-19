<?php

namespace App\Approval;

use App\Approval\Exceptions\ReminderNotAllowedException;
use App\Dashboard\InReviewSnapshot;
use App\Enums\DocumentStatus;
use App\Mail\StuckDocumentReminderMail;
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
 * One reminder log (document_reminders) serves the manual button and the
 * hourly scheduler. The manual button is locked for COOLDOWN_HOURS after ANY
 * reminder (manual or automatic) went to the approver at the document's
 * current step; a reminder for an earlier visit to a step never counts. A
 * manual send writes no slot, so it cannot move the automatic 24 hour
 * schedule (see AutomaticStepReminders).
 */
class StuckDocumentReminders
{
    public const int COOLDOWN_HOURS = 12;

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
        return $this->cooldownsFor([$document->id])[$document->id]['availableAt'] ?? null;
    }

    /**
     * The cooldown for each document id that has one running (ids that can be
     * reminded now are left out). A reminder only counts when it was about the
     * document's current visit to its step (or predates the log recording one),
     * and the SDAO overdue alert, which goes to SDAO and not the approver,
     * never counts. Two queries for the whole list.
     *
     * @param  array<int, int>  $documentIds
     * @return array<int, array{availableAt: CarbonInterface, lastSentAt: CarbonInterface, kind: string}>
     */
    public function cooldownsFor(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $arrivals = Document::query()
            ->with('latestTransition')
            ->whereIn('id', $documentIds)
            ->get()
            ->mapWithKeys(fn (Document $document) => [$document->id => $document->latestTransition?->id]);

        $recent = DocumentReminder::query()
            ->whereIn('document_id', $documentIds)
            ->where('kind', '!=', DocumentReminder::KIND_SDAO_OVERDUE)
            ->where('created_at', '>', now()->subHours(self::COOLDOWN_HOURS))
            ->get()
            ->groupBy('document_id');

        $cooldowns = [];

        foreach ($recent as $documentId => $rows) {
            $arrival = $arrivals[$documentId] ?? null;
            $latest = $rows
                ->filter(fn (DocumentReminder $row) => $row->arrival_transition_id === null || $row->arrival_transition_id === $arrival)
                ->sortByDesc('created_at')
                ->first();

            if ($latest instanceof DocumentReminder) {
                $cooldowns[(int) $documentId] = [
                    'availableAt' => $latest->created_at->copy()->addHours(self::COOLDOWN_HOURS),
                    'lastSentAt' => $latest->created_at,
                    'kind' => $latest->kind,
                ];
            }
        }

        return $cooldowns;
    }

    /**
     * The notification for a document, worded by where it stands: a nudge, or
     * an overdue alert once the approver's step has passed its lead time.
     */
    public function notificationFor(Document $document, bool $toApprover): StuckDocumentReminderNotification
    {
        $document->loadMissing('latestTransition');
        $deadline = $toApprover ? StepDeadline::for($document) : null;

        if ($deadline?->isOverdue()) {
            return new StuckDocumentReminderNotification(
                $document,
                true,
                InReviewSnapshot::idleDays($document),
                StuckDocumentReminderMail::VARIANT_OVERDUE,
                $deadline->daysOverdue(),
                $deadline->leadTimeLabel(),
            );
        }

        return new StuckDocumentReminderNotification($document, $toApprover, InReviewSnapshot::idleDays($document));
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
            // Serialise two admins clicking Remind at once (and the scheduler),
            // as the engine does for approvals.
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [DocumentStatus::InReview, DocumentStatus::Returned], true)) {
                throw new ReminderNotAllowedException('This document is no longer open, so there is no one to remind.');
            }

            $cooldown = $this->cooldownsFor([$locked->id])[$locked->id] ?? null;

            if ($cooldown !== null) {
                $sentAt = DisplayTimezone::convert($cooldown['lastSentAt']->copy())->format('n/j/Y g:i A');
                $nextAt = DisplayTimezone::convert($cooldown['availableAt']->copy())->format('n/j/Y g:i A');

                throw new ReminderNotAllowedException("A reminder for this step was already sent on {$sentAt}. You can send another after {$nextAt}.");
            }

            $recipients = $this->recipients($locked);

            if ($recipients->isEmpty()) {
                throw new ReminderNotAllowedException('No one holds this step yet, so there is no one to remind.');
            }

            $locked->load('latestTransition');
            $notification = $this->notificationFor($locked, $this->isWaitingOnApprover($locked));

            foreach ($recipients as $recipient) {
                $recipient->notify($notification);
            }

            $this->record($locked, DocumentReminder::KIND_MANUAL, $recipients->count(), $actor->id);

            return $recipients;
        });
    }

    /**
     * Writes the log row for a reminder about the document's current visit to
     * its step. The one place a row is created, for manual and automatic alike.
     *
     * @param  int|null  $slot  the 24 hour mark for an automatic row; null for a manual one
     */
    public function record(Document $document, string $kind, int $recipientCount, ?int $sentBy = null, ?int $slot = null): DocumentReminder
    {
        $document->loadMissing('latestTransition');

        return DocumentReminder::create([
            'document_id' => $document->id,
            'sent_by' => $sentBy,
            'kind' => $kind,
            'step_position' => $document->status === DocumentStatus::InReview ? $document->current_step_position : null,
            'arrival_transition_id' => $document->latestTransition?->id,
            'slot' => $slot,
            'recipient_count' => $recipientCount,
            'created_at' => now(),
        ]);
    }
}
