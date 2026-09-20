<?php

namespace App\Organizations;

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Identity\RoleDirectory;
use App\Models\OfficerChangeRequest;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OfficerChangeRequestedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A current president/secretary's self-service alternative to the adviser's
 * direct BindOrganizationOfficer path (which is unchanged) — files a request
 * naming a specific nominee for a specific seat, sitting Pending until an
 * SDAO admin finalizes it (App\Organizations\Admin\ApproveOfficerChange /
 * DeclineOfficerChange). Strictly additive: nothing here touches
 * organization_memberships, and the adviser can still bind directly at any
 * time, including while a request for the same seat is Pending.
 *
 * Takes no Organization parameter — the actor's own active membership IS the
 * authorization boundary (the "query-scope-as-authorization" idiom used
 * elsewhere in this app, e.g. MyOrganizationController::show()), so filing a
 * request needs no new policy method and manageOfficers() stays untouched.
 */
class RequestOfficerChange
{
    public function __construct(
        private readonly OrganizationMembershipService $membershipService,
        private readonly EligibleOfficerCandidates $candidates,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, OfficerPosition $position, User $nominee, ?string $reason = null): OfficerChangeRequest
    {
        $actorMembership = $this->membershipService->activeMembershipWithOrganizationFor($actor);

        if ($actorMembership === null) {
            throw new AuthorizationException('Only an active officer of an organization may request an officer change.');
        }

        $organization = $actorMembership->organization;

        $hasPendingRequest = OfficerChangeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->pending()
            ->exists();

        if ($hasPendingRequest) {
            throw ValidationException::withMessages([
                'position' => 'There is already a pending change request for this position.',
            ]);
        }

        if (! $nominee->isVerifiedAccount()) {
            throw ValidationException::withMessages([
                'nominee_id' => 'This student\'s account has not been SDAO-verified yet.',
            ]);
        }

        if ($this->membershipService->hasActiveMembershipElsewhere($nominee, $organization)) {
            throw ValidationException::withMessages([
                'nominee_id' => 'This student is already an active officer of a different organization.',
            ]);
        }

        $alreadyHoldsPosition = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->where('user_id', $nominee->id)
            ->where('is_active', true)
            ->exists();

        if ($alreadyHoldsPosition) {
            throw ValidationException::withMessages([
                'nominee_id' => 'They already hold this position.',
            ]);
        }

        if (! $this->candidates->matches($organization, $nominee)) {
            throw ValidationException::withMessages([
                'nominee_id' => 'This student is not eligible to be bound as an officer right now.',
            ]);
        }

        $outgoingUserId = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('position', $position->value)
            ->where('is_active', true)
            ->value('user_id');

        $changeRequest = OfficerChangeRequest::create([
            'organization_id' => $organization->id,
            'requested_by' => $actor->id,
            'position' => $position->value,
            'nominee_id' => $nominee->id,
            'outgoing_user_id' => $outgoingUserId,
            'reason' => $reason,
            'status' => OfficerChangeRequestStatus::Pending,
        ]);

        $this->notifySdao($changeRequest);

        return $changeRequest;
    }

    /**
     * Best-effort hand-off notification to every SDAO member — the only
     * audience that can finalize this request. Never blocks the request
     * itself; a mail-provider failure is logged, not surfaced to the
     * requesting officer.
     */
    private function notifySdao(OfficerChangeRequest $changeRequest): void
    {
        $recipients = $this->roleDirectory->sdaoMembers();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, new OfficerChangeRequestedNotification($changeRequest));
        } catch (\Throwable $e) {
            Log::error('Officer-change-requested notification failed to dispatch', [
                'officer_change_request_id' => $changeRequest->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
