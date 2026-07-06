<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\ProposalVariant;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /** @return HasMany<DocumentTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(DocumentTransition::class)->orderBy('id');
    }

    /** @return HasOne<OrganizationRegistrationDetail, $this> */
    public function registrationDetail(): HasOne
    {
        return $this->hasOne(OrganizationRegistrationDetail::class);
    }
}
