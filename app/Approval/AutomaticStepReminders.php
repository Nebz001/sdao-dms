<?php

namespace App\Approval;

use App\Dashboard\InReviewSnapshot;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Identity\RoleDirectory;
use App\Mail\StuckDocumentReminderMail;
use App\Models\Document;
use App\Models\DocumentReminder;
use App\Models\User;
use App\Notifications\StuckDocumentReminderNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the hourly `documents:send-step-reminders` command does.
 *
 * For every document waiting at an approver's step, counted from the moment it
 * reached that step (its latest transition):
 *
 * - Once every 24 hours the current approver(s) get a reminder. Once the step
 *   has passed its lead time the same message becomes an overdue alert, with
 *   different wording. It stops by itself when the approver acts, because the
 *   document then has a newer transition or has left the step.
 * - The first time a step is found past its lead time, SDAO gets one overdue
 *   alert (never repeated for that visit to the step).
 *
 * The schedule is anchored on the arrival, not on the last send: slot N is
 * the Nth 24 hour mark since arrival. Every send is a row in the reminder
 * log, written BEFORE the notification and unique on (arrival, slot), so a
 * restart, a missed hour or two overlapping runs can never send a slot twice.
 * A manual Remind writes no slot, so it never moves this schedule.
 */
class AutomaticStepReminders
{
    public const int INTERVAL_HOURS = 24;

    /** The slot number that stands for the SDAO overdue alert. */
    public const int SDAO_ALERT_SLOT = 0;

    public function __construct(
        private readonly StuckDocumentReminders $reminders,
        private readonly RoleDirectory $directory,
    ) {}

    /**
     * @return array{reminders: int, overdue_alerts: int}
     */
    public function run(): array
    {
        $summary = ['reminders' => 0, 'overdue_alerts' => 0];

        Document::query()
            ->where('status', DocumentStatus::InReview->value)
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$summary) {
                $result = $this->process($id);
                $summary['reminders'] += $result['reminder'] ? 1 : 0;
                $summary['overdue_alerts'] += $result['sdao_alert'] ? 1 : 0;
            });

        return $summary;
    }

    /**
     * @return array{reminder: bool, sdao_alert: bool}
     */
    private function process(int $documentId): array
    {
        return DB::transaction(function () use ($documentId) {
            $result = ['reminder' => false, 'sdao_alert' => false];

            // The same lock a manual Remind takes, so the two cannot interleave.
            $document = Document::query()
                ->with(['organization', 'workflowTemplate.steps', 'latestTransition'])
                ->whereKey($documentId)
                ->lockForUpdate()
                ->first();

            if (! $document instanceof Document || $document->status !== DocumentStatus::InReview) {
                return $result;
            }

            $deadline = StepDeadline::for($document);

            if ($deadline === null) {
                return $result;
            }

            $recipients = $this->reminders->recipients($document);
            $slot = intdiv($deadline->arrivedAt->diffInSeconds(now(), true), self::INTERVAL_HOURS * 3600);

            if ($slot >= 1 && $recipients->isNotEmpty()) {
                $kind = $deadline->isOverdue() ? DocumentReminder::KIND_OVERDUE : DocumentReminder::KIND_AUTOMATIC;

                if ($this->claim($document, $kind, $recipients->count(), $slot)) {
                    $notification = $this->reminders->notificationFor($document, true);
                    $recipients->each(fn (User $recipient) => $recipient->notify($notification));
                    $result['reminder'] = true;
                }
            }

            if ($deadline->isOverdue()) {
                $result['sdao_alert'] = $this->alertSdao($document, $deadline, $recipients);
            }

            return $result;
        });
    }

    /**
     * Sends SDAO the one overdue alert for this visit to the step. People who
     * already got the overdue reminder as the approver (the SDAO step itself)
     * are left out, so nobody is told twice.
     *
     * @param  Collection<int, User>  $approvers
     */
    private function alertSdao(Document $document, StepDeadline $deadline, Collection $approvers): bool
    {
        $audience = $this->directory->sdaoMembers()->reject(fn (User $user) => $approvers->contains('id', $user->id))->values();

        if (! $this->claim($document, DocumentReminder::KIND_SDAO_OVERDUE, $audience->count(), self::SDAO_ALERT_SLOT)) {
            return false;
        }

        $notification = new StuckDocumentReminderNotification(
            $document,
            true,
            InReviewSnapshot::idleDays($document),
            StuckDocumentReminderMail::VARIANT_SDAO_OVERDUE,
            $deadline->daysOverdue(),
            $deadline->leadTimeLabel(),
            $this->waitingOn($document, $approvers),
        );

        $audience->each(fn (User $user) => $user->notify($notification));

        return true;
    }

    /**
     * Takes the (arrival, slot) place in the reminder log. False when it is
     * already taken: that slot went out on an earlier run.
     */
    private function claim(Document $document, string $kind, int $recipientCount, int $slot): bool
    {
        $arrivalId = $document->latestTransition?->id;

        $taken = DocumentReminder::query()
            ->where('arrival_transition_id', $arrivalId)
            ->where('slot', $slot)
            ->exists();

        if ($taken) {
            return false;
        }

        try {
            // Its own savepoint, so a lost race rolls back just this insert.
            DB::transaction(fn () => $this->reminders->record($document, $kind, $recipientCount, null, $slot));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, User>  $approvers
     */
    private function waitingOn(Document $document, Collection $approvers): string
    {
        $step = $document->workflowTemplate?->steps->firstWhere('position', $document->current_step_position);

        if ($step?->role === Role::SdaoMember) {
            return 'the SDAO members who have not approved it yet';
        }

        return $approvers->isNotEmpty()
            ? $approvers->pluck('name')->join(' and ')
            : 'an approver who has not been assigned yet';
    }
}
