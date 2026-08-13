<?php

namespace App\Organizations;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\JoinRequestStatus;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationJoinRequest;
use App\Models\User;
use App\Notifications\JoinRequestReceivedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A student's self-service alternative to being adviser-bound: search for an
 * organization already in the system and ask to join it. Unlike every other
 * submission in this app, filing a request does NOT require
 * `isVerifiedAccount()` — a request is not a document submission, just
 * "asking to be let in" (same spirit as privately asking an adviser today).
 * `isVerifiedAccount()` is instead enforced at approval time
 * (App\Organizations\ApproveJoinRequest), mirroring
 * BindOrganizationOfficer's own guard.
 */
class RequestToJoinOrganization
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(User $actor, Organization $organization): OrganizationJoinRequest
    {
        // One organization per student (Phase 2 item 4) — same rule
        // SubmitOrganizationRegistration enforces for the founding path.
        if ($this->membershipService->hasActiveMembershipElsewhere($actor)) {
            throw ValidationException::withMessages([
                'organization' => 'You are already an active officer of an organization.',
            ]);
        }

        $hasInFlightProposal = Document::query()
            ->where('submitted_by', $actor->id)
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->exists();

        if ($hasInFlightProposal) {
            throw ValidationException::withMessages([
                'organization' => 'You already have an in-progress organization registration.',
            ]);
        }

        $hasPendingRequest = OrganizationJoinRequest::query()
            ->where('user_id', $actor->id)
            ->pending()
            ->exists();

        if ($hasPendingRequest) {
            throw ValidationException::withMessages([
                'organization' => 'You already have a pending request to join an organization.',
            ]);
        }

        $joinRequest = OrganizationJoinRequest::create([
            'user_id' => $actor->id,
            'organization_id' => $organization->id,
            'status' => JoinRequestStatus::Pending,
        ]);

        $this->notifyOrganization($organization, $joinRequest);

        return $joinRequest;
    }

    /**
     * Best-effort hand-off notification (invariant #9's pattern, extended to
     * a non-Document event) to whoever can act on this request — the org's
     * adviser, plus any active officers per the task's "officer or adviser"
     * scope. Never blocks the request itself; a mail-provider failure is
     * logged, not surfaced to the student.
     */
    private function notifyOrganization(Organization $organization, OrganizationJoinRequest $joinRequest): void
    {
        $recipients = $this->membershipService->activeOfficersFor($organization);

        try {
            $adviser = $this->roleDirectory->adviserFor($organization);
            $recipients = $recipients->push($adviser)->unique('id');
        } catch (\Throwable) {
            // No adviser bound yet — officers alone still get notified.
        }

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new JoinRequestReceivedNotification($joinRequest));
        } catch (\Throwable $e) {
            Log::error('Join-request-received notification failed to dispatch', [
                'join_request_id' => $joinRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
