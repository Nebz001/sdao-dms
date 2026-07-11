<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\TransitionAction;
use Database\Factories\DocumentTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit log entry. Never updated or deleted (invariant #7).
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $actor_id
 * @property TransitionAction $action
 * @property DocumentStatus|null $from_status
 * @property DocumentStatus $to_status
 * @property int|null $step_position
 * @property string|null $comment
 * @property array<int, string>|null $flagged_sections
 */
#[Fillable(['document_id', 'actor_id', 'action', 'from_status', 'to_status', 'step_position', 'comment', 'flagged_sections', 'created_at'])]
class DocumentTransition extends Model
{
    public $timestamps = false;

    protected $casts = [
        'action' => TransitionAction::class,
        'from_status' => DocumentStatus::class,
        'to_status' => DocumentStatus::class,
        'flagged_sections' => 'array',
        'created_at' => 'datetime',
    ];

    /** @use HasFactory<DocumentTransitionFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
