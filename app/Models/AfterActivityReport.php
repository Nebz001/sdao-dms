<?php

namespace App\Models;

use Database\Factories\AfterActivityReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $document_id
 * @property int $activity_proposal_id
 * @property string $narrative
 * @property string|null $outcomes
 * @property int|null $participant_count
 */
#[Fillable(['document_id', 'activity_proposal_id', 'narrative', 'outcomes', 'participant_count'])]
class AfterActivityReport extends Model
{
    /** @use HasFactory<AfterActivityReportFactory> */
    use HasFactory;

    protected $casts = [
        'participant_count' => 'integer',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<ActivityProposal, $this> */
    public function activityProposal(): BelongsTo
    {
        return $this->belongsTo(ActivityProposal::class);
    }

    /** @return HasMany<AfterActivityReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(AfterActivityReportAttachment::class);
    }
}
