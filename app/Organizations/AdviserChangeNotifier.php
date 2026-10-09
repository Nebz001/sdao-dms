<?php

namespace App\Organizations;

use App\Enums\AdviserTermOutcome;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Notifications\AdviserAssignedNotification;
use App\Notifications\AdviserRoleEndedNotification;
use App\Notifications\OrganizationAdviserChangedNotification;
use App\Organizations\Admin\AdviserChange;
use Illuminate\Support\Facades\Log;

/**
 * The three notices that follow an adviser change, sent AFTER the change has
 * committed and best-effort: a mail-provider failure is logged, never surfaced,
 * so a notification problem can never undo or block the swap (same rule as
 * OfficerSeatNotifier).
 *
 *  - the NEW adviser: one notice listing what is already waiting on them;
 *  - the OLD adviser: their role for that organization ended — never who replaced them;
 *  - the organization's ACTIVE OFFICERS: who their adviser now is.
 */
class AdviserChangeNotifier
{
    public function __construct(private readonly OrganizationMembershipService $membershipService) {}

    public function announce(AdviserChange $change): void
    {
        $this->safely('adviser-assigned', $change, fn () => $this->welcomeIncoming($change));

        if ($change->outgoing !== null) {
            $this->safely('adviser-role-ended', $change, fn () => $change->outgoing->notify(
                new AdviserRoleEndedNotification($change->organization, $change->outgoingOutcome === AdviserTermOutcome::Deactivated),
            ));
        }

        $this->safely('organization-adviser-changed', $change, function () use ($change) {
            foreach ($this->membershipService->activeOfficersFor($change->organization) as $officer) {
                $officer->notify(new OrganizationAdviserChangedNotification($change->organization, $change->incoming->name));
            }
        });
    }

    /**
     * Documents sitting at an adviser step right now (their current step's role
     * is the adviser) plus pending join requests, resolved by what is waiting
     * today rather than by who the adviser used to be.
     */
    private function welcomeIncoming(AdviserChange $change): void
    {
        $waiting = Document::query()
            ->where('organization_id', $change->organization->id)
            ->where('status', DocumentStatus::InReview->value)
            ->whereExists(fn ($steps) => $steps->from('workflow_steps')
                ->whereColumn('workflow_steps.workflow_template_id', 'documents.workflow_template_id')
                ->whereColumn('workflow_steps.position', 'documents.current_step_position')
                ->where('workflow_steps.role', Role::Adviser->value))
            ->orderBy('created_at');

        $count = (clone $waiting)->count();
        $listed = $waiting
            ->limit(AdviserAssignedNotification::LISTED_DOCUMENTS)
            ->get(['id', 'title', 'form_type'])
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'form_type' => $document->form_type->value,
            ])
            ->all();

        $joinRequests = OrganizationJoinRequest::query()
            ->where('organization_id', $change->organization->id)
            ->pending()
            ->count();

        $change->incoming->notify(new AdviserAssignedNotification($change->organization, $listed, $count, $joinRequests));
    }

    private function safely(string $notice, AdviserChange $change, \Closure $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error("Adviser change notification failed to dispatch ({$notice})", [
                'organization_id' => $change->organization->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
