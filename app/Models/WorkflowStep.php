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
 */
#[Fillable(['workflow_template_id', 'position', 'role', 'required_approvals'])]
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    protected $casts = [
        'role' => Role::class,
        'position' => 'integer',
        'required_approvals' => 'integer',
    ];

    /** @return BelongsTo<WorkflowTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }
}
