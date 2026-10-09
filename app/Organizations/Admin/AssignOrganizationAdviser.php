<?php

namespace App\Organizations\Admin;

use App\Enums\AdviserTermOutcome;
use App\Enums\Role;
use App\Identity\Admin\AccountDeactivator;
use App\Models\AdviserTerm;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Organizations\AdviserChangeNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one way an organization's adviser is changed by SDAO, whether the
 * incoming adviser is a brand-new account (ProvisionApprover) or an existing
 * unassigned one from the pool (Admin\OrganizationAdviserController). Same idea
 * as OrganizationMembershipService::runSeatChange(): ONE transaction that locks
 * the organization row first, so two swaps (or a swap and an officer change) on
 * the same organization queue behind each other, and every guard is re-read
 * under that lock.
 *
 * Inside it: the outgoing adviser's term is closed (never deleted) and their
 * role row is unbound back to the pool, the incoming adviser's role row is bound,
 * and a new term is opened — all or nothing. The outgoing account either stays
 * in the pool or is deactivated in the same transaction (SDAO's choice, as with
 * SDAO replacement). The partial unique indexes on role_assignments and
 * adviser_terms are the last line of defence: if one still fires, the loser gets
 * a plain "someone else just changed this" validation error, not a 500.
 *
 * Notices go out AFTER the outermost transaction commits: swap() only changes
 * data and returns what it did; execute() is swap() plus the announcement, for
 * callers with no transaction of their own.
 */
class AssignOrganizationAdviser
{
    public function __construct(
        private readonly AccountDeactivator $deactivator,
        private readonly AdviserChangeNotifier $notifier,
    ) {}

    /**
     * @param  AdviserTermOutcome  $outgoingOutcome  What happens to the current adviser, if there is one
     * @param  bool  $verifyOutgoing  When true, refuse unless the organization's current adviser is still
     *                                $expectedOutgoingUserId (null = the page showed no adviser). Set by the
     *                                screens, so a confirmation about one adviser can never act on another.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, Organization $organization, User $incoming, AdviserTermOutcome $outgoingOutcome, bool $verifyOutgoing = false, ?int $expectedOutgoingUserId = null): AdviserChange
    {
        $change = $this->swap($actor, $organization, $incoming, $outgoingOutcome, $verifyOutgoing, $expectedOutgoingUserId);

        $this->notifier->announce($change);

        return $change;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function swap(User $actor, Organization $organization, User $incoming, AdviserTermOutcome $outgoingOutcome, bool $verifyOutgoing = false, ?int $expectedOutgoingUserId = null): AdviserChange
    {
        if (! $actor->roleAssignments()->where('role', Role::SdaoMember)->exists()) {
            throw new AuthorizationException('Only an SDAO member may change an organization\'s adviser.');
        }

        try {
            return DB::transaction(function () use ($actor, $organization, $incoming, $outgoingOutcome, $verifyOutgoing, $expectedOutgoingUserId) {
                $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

                if ($lockedOrganization === null) {
                    throw ValidationException::withMessages(['organization' => 'That organization no longer exists.']);
                }

                $incomingAssignment = $this->lockIncoming($incoming, $lockedOrganization);

                $outgoingAssignment = RoleAssignment::query()
                    ->where('role', Role::Adviser)
                    ->where('organization_id', $lockedOrganization->id)
                    ->lockForUpdate()
                    ->first();

                if ($verifyOutgoing && $outgoingAssignment?->user_id !== $expectedOutgoingUserId) {
                    throw ValidationException::withMessages([
                        'adviser' => 'This organization\'s adviser changed while you were on this page. Reload to see the current adviser, then try again.',
                    ]);
                }

                $now = now();
                $outgoing = null;

                if ($outgoingAssignment !== null) {
                    $outgoing = User::query()->findOrFail($outgoingAssignment->user_id);
                    $this->closeTerm($outgoingAssignment, $actor, $outgoingOutcome, $now);

                    // Unbound, not deleted: back to the pool, so an in-flight
                    // registration naming them still validates (see
                    // ProvisionApprover's old retireIncumbent() note).
                    $outgoingAssignment->update(['organization_id' => null]);

                    if ($outgoingOutcome === AdviserTermOutcome::Deactivated) {
                        $this->deactivator->deactivate($outgoing, $actor, "Replaced as adviser of {$lockedOrganization->name}");
                    }
                }

                $incomingAssignment->update(['organization_id' => $lockedOrganization->id]);

                AdviserTerm::create([
                    'user_id' => $incoming->id,
                    'organization_id' => $lockedOrganization->id,
                    'started_at' => $now,
                    'started_by' => $actor->id,
                ]);

                return new AdviserChange(
                    organization: $lockedOrganization,
                    incoming: $incoming,
                    outgoing: $outgoing,
                    outgoingOutcome: $outgoing === null ? null : $outgoingOutcome,
                );
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'adviser' => 'Someone else just changed this organization\'s adviser. Reload to see the current adviser, then try again.',
            ]);
        }
    }

    /**
     * The incoming adviser's role row, locked, and only if they are a free,
     * active pool adviser right now.
     *
     * @throws ValidationException
     */
    private function lockIncoming(User $incoming, Organization $organization): RoleAssignment
    {
        $assignment = RoleAssignment::query()
            ->where('role', Role::Adviser)
            ->where('user_id', $incoming->id)
            ->lockForUpdate()
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages(['adviser' => 'That account is not an adviser.']);
        }

        if (User::query()->whereKey($incoming->id)->whereNotNull('deactivated_at')->exists()) {
            throw ValidationException::withMessages(['adviser' => 'That adviser account is deactivated. Choose an active adviser.']);
        }

        if ($assignment->organization_id === $organization->id) {
            throw ValidationException::withMessages(['adviser' => 'That adviser already advises this organization.']);
        }

        if ($assignment->organization_id !== null) {
            throw ValidationException::withMessages(['adviser' => 'That adviser is already assigned to another organization. Choose an unassigned adviser.']);
        }

        return $assignment;
    }

    /**
     * Closes the outgoing adviser's open term. A bound adviser with no term on
     * record (bound before terms existed, or by a seeder) gets one written
     * already-closed, so the history still shows they held the seat.
     */
    private function closeTerm(RoleAssignment $assignment, User $actor, AdviserTermOutcome $outcome, \DateTimeInterface $at): void
    {
        $closed = AdviserTerm::query()
            ->where('user_id', $assignment->user_id)
            ->where('organization_id', $assignment->organization_id)
            ->open()
            ->update(['ended_at' => $at, 'ended_by' => $actor->id, 'end_outcome' => $outcome->value]);

        if ($closed === 0) {
            AdviserTerm::create([
                'user_id' => $assignment->user_id,
                'organization_id' => $assignment->organization_id,
                'started_at' => $assignment->created_at ?? $at,
                'ended_at' => $at,
                'ended_by' => $actor->id,
                'end_outcome' => $outcome,
            ]);
        }
    }
}
