<?php

namespace App\Registrations;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Models\Document;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * Withdraws every in-flight (Draft/InReview/Returned) OrganizationRegistration
 * document submitted by the given user — called before deleting their account
 * (see ProfileController::destroy()) so a pending registration can never
 * survive its submitter: `documents.submitted_by` is nullOnDelete, so without
 * this the document would simply sit in the review queue with no submitter,
 * and ApproveOrganizationRegistration would hit a NOT NULL constraint trying
 * to bind a null user_id as President once approved.
 *
 * Scoped to Registration specifically — it's the only form type whose
 * approval creates a new binding (OrganizationMembership) keyed off
 * submitted_by. A future admin-initiated account deletion should call this
 * same action rather than re-implementing it.
 */
class WithdrawInFlightRegistrations
{
    private const string REASON = 'Withdrawn automatically: the submitting account was deleted.';

    public function __construct(private readonly ApprovalEngine $engine) {}

    public const string DEACTIVATED_REASON = 'Withdrawn automatically: the submitting account was deactivated.';

    /**
     * @param  string|null  $reason  what the transition log records; defaults to the account-deleted wording
     * @param  bool  $onlyWhereNoOfficers  withdraw only registrations whose organization has no active officer left —
     *                                     the founding case, where the submitter is the ONLY person with access
     *                                     (OrganizationMembershipService::canActOnDocument). Used when deactivating an
     *                                     account, run after the seat is closed.
     */
    public function execute(User $user, ?string $reason = null, bool $onlyWhereNoOfficers = false): void
    {
        Document::query()
            ->where('form_type', FormType::OrganizationRegistration)
            ->where('submitted_by', $user->id)
            ->whereIn('status', [DocumentStatus::Draft, DocumentStatus::InReview, DocumentStatus::Returned])
            ->when($onlyWhereNoOfficers, fn ($query) => $query->whereNotIn(
                'organization_id',
                OrganizationMembership::query()->active()->select('organization_id'),
            ))
            ->each(fn (Document $document) => $this->engine->withdraw($document, $reason ?? self::REASON));
    }
}
