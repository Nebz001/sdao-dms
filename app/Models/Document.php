<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property FormType $form_type
 * @property ProposalVariant|null $variant
 * @property string $title
 * @property DocumentStatus $status
 * @property int|null $current_step_position
 * @property int $organization_id
 * @property int|null $workflow_template_id
 * @property int|null $submitted_by
 */
#[Fillable(['form_type', 'variant', 'title', 'status', 'current_step_position', 'organization_id', 'workflow_template_id', 'submitted_by'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $casts = [
        'form_type' => FormType::class,
        'variant' => ProposalVariant::class,
        'status' => DocumentStatus::class,
        'current_step_position' => 'integer',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The documents an officer may see in their own lists and nav counts: every
     * document of an organization they are an ACTIVE officer of, plus — only
     * for the founding case — the registration they personally submitted while
     * that org has no active officers yet. This is the list-side twin of
     * OrganizationMembershipService::canActOnDocument(); keep the two in
     * lockstep (DocumentHistoryTest pins that every listed row passes the
     * view gate). A removed officer's authorship grants nothing.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleToOfficer(Builder $query, User $user): void
    {
        $activeOrganizationIds = OrganizationMembership::query()
            ->where('is_active', true)
            ->select('organization_id');

        $query->where(fn (Builder $q) => $q
            ->whereIn('organization_id', (clone $activeOrganizationIds)->where('user_id', $user->id))
            ->orWhere(fn (Builder $founding) => $founding
                ->where('form_type', FormType::OrganizationRegistration->value)
                ->where('submitted_by', $user->id)
                ->whereNotIn('organization_id', $activeOrganizationIds)));
    }

    /**
     * Still moving through the chain — see DocumentStatus::isInFlight() for
     * why Rejected is deliberately excluded.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeInFlight(Builder $query): void
    {
        $inFlightValues = collect(DocumentStatus::cases())
            ->filter(fn (DocumentStatus $status) => $status->isInFlight())
            ->map(fn (DocumentStatus $status) => $status->value);

        $query->whereIn('status', $inFlightValues);
    }

    /** @return BelongsTo<WorkflowTemplate, $this> */
    public function workflowTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<DocumentStepApproval, $this> */
    public function stepApprovals(): HasMany
    {
        return $this->hasMany(DocumentStepApproval::class);
    }

    /** @return HasMany<DocumentRemark, $this> */
    public function remarks(): HasMany
    {
        return $this->hasMany(DocumentRemark::class)->orderBy('id');
    }

    /** @return HasMany<DocumentTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(DocumentTransition::class)->orderBy('id');
    }

    /**
     * The most recent transition — the honest "last activity" clock.
     * `documents.updated_at` is NOT reliable for this: a partial SDAO
     * approval (first of two required) writes a DocumentTransition but
     * ApprovalEngine::approve() returns early without saving the Document
     * (invariant #3's split-decision handling), so updated_at can lag behind
     * real activity.
     *
     * @return HasOne<DocumentTransition, $this>
     */
    public function latestTransition(): HasOne
    {
        return $this->hasOne(DocumentTransition::class)->latestOfMany('created_at');
    }

    /** @return HasOne<OrganizationRegistrationDetail, $this> */
    public function registrationDetail(): HasOne
    {
        return $this->hasOne(OrganizationRegistrationDetail::class);
    }

    /** @return HasOne<ActivityCalendar, $this> */
    public function activityCalendar(): HasOne
    {
        return $this->hasOne(ActivityCalendar::class);
    }

    /** @return HasOne<ActivityProposal, $this> */
    public function activityProposal(): HasOne
    {
        return $this->hasOne(ActivityProposal::class);
    }

    /** @return HasOne<AfterActivityReport, $this> */
    public function afterActivityReport(): HasOne
    {
        return $this->hasOne(AfterActivityReport::class);
    }

    /**
     * Uploaded files across all attachment slots for this document (Phase 2
     * item 8) — generic across all form types, see App\Attachments\AttachmentSlots.
     *
     * @return HasMany<DocumentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class);
    }
}
