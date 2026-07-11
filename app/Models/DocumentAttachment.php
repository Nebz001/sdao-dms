<?php

namespace App\Models;

use Database\Factories\DocumentAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single uploaded file filling one named attachment slot (Phase 2 item 8).
 * Generic across all form types — keyed to Document, not a per-form table —
 * with `slot_key` identifying which slot (see App\Attachments\AttachmentSlots)
 * this row fills.
 *
 * @property int $id
 * @property int $document_id
 * @property string $slot_key
 * @property string $original_filename
 * @property string $path
 * @property string $disk
 * @property string $mime_type
 * @property int|null $size
 * @property int|null $uploaded_by
 */
#[Fillable(['document_id', 'slot_key', 'original_filename', 'path', 'disk', 'mime_type', 'size', 'uploaded_by'])]
class DocumentAttachment extends Model
{
    /** @use HasFactory<DocumentAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
