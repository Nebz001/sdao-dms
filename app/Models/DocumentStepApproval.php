<?php

namespace App\Models;

use Database\Factories\DocumentStepApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property int $workflow_step_id
 * @property int $step_position
 * @property int $user_id
 */
#[Fillable(['document_id', 'workflow_step_id', 'step_position', 'user_id'])]
class DocumentStepApproval extends Model
{
    /** @use HasFactory<DocumentStepApprovalFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<WorkflowStep, $this> */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
