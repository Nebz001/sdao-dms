<?php

namespace App\Models;

use Database\Factories\ProposalAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $activity_proposal_id
 * @property string $original_filename
 * @property string $path
 * @property string $disk
 * @property string $mime_type
 * @property int|null $size
 */
#[Fillable(['activity_proposal_id', 'original_filename', 'path', 'disk', 'mime_type', 'size'])]
class ProposalAttachment extends Model
{
    /** @use HasFactory<ProposalAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<ActivityProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class, 'activity_proposal_id');
    }
}
