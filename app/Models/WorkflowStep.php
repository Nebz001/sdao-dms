<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workflow_template_id
 * @property int $position
 * @property Role $role
 * @property int $required_approvals
 * @property int $lead_time_days Days an approver has for this step, counted from the document's latest transition.
 */
#[Fillable(['workflow_template_id', 'position', 'role', 'required_approvals', 'lead_time_days'])]
class WorkflowStep extends Model
{
    /** Days a step is given unless SDAO changes it. */
    public const int DEFAULT_LEAD_TIME_DAYS = 3;

    public const int MAX_LEAD_TIME_DAYS = 60;

    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $casts = [
        'role' => Role::class,
        'position' => 'integer',
        'required_approvals' => 'integer',
        'lead_time_days' => 'integer',
    ];

    /** @return BelongsTo<WorkflowTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }
}
