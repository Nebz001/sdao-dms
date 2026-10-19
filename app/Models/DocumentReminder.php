<?php

namespace App\Models;

use Database\Factories\DocumentReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The one reminder log. A manual "Remind", an automatic reminder, an overdue
 * alert to the approver and the single SDAO overdue alert each write a row.
 * Append-only. The cooldown reads the newest approver-facing row for a visit
 * to a step; the unique (arrival_transition_id, slot) pair is what stops a
 * restart or a double run from sending an automatic reminder twice (see
 * App\Approval\StepReminders).
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $sent_by
 * @property string $kind manual|automatic|overdue|sdao_overdue
 * @property int|null $step_position
 * @property int|null $arrival_transition_id The transition that put the document at the step this row is about.
 * @property int|null $slot 24 hour mark since arrival (1 = day one); 0 = the SDAO overdue alert; null = manual.
 * @property int $recipient_count
 */
#[Fillable(['document_id', 'sent_by', 'kind', 'step_position', 'arrival_transition_id', 'slot', 'recipient_count', 'created_at'])]
class DocumentReminder extends Model
{
    /** @use HasFactory<DocumentReminderFactory> */
    use HasFactory;

    public const string KIND_MANUAL = 'manual';

    public const string KIND_AUTOMATIC = 'automatic';

    public const string KIND_OVERDUE = 'overdue';

    public const string KIND_SDAO_OVERDUE = 'sdao_overdue';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'slot' => 'integer',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
