<?php

namespace App\Models;

use Database\Factories\DocumentReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "Remind" sent from the Stuck Documents page. Append-only; the newest row
 * per document is what the 24 hour limit reads (see StuckDocumentReminders).
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $sent_by
 * @property int $recipient_count
 */
#[Fillable(['document_id', 'sent_by', 'recipient_count', 'created_at'])]
class DocumentReminder extends Model
{
    /** @use HasFactory<DocumentReminderFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
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
