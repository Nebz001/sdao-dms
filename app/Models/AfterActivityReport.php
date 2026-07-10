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
 * @property string $summary
 * @property string|null $outcomes
 * @property int|null $participant_count
 * @property array<int, string>|null $activity_chairs
 * @property string|null $prepared_by
 * @property string|null $event_program
 * @property int|null $target_participants_percentage
 */
#[Fillable(['document_id', 'activity_proposal_id', 'summary', 'outcomes', 'participant_count', 'activity_chairs', 'prepared_by', 'event_program', 'target_participants_percentage'])]
class AfterActivityReport extends Model
{
    /** @use HasFactory<AfterActivityReportFactory> */
    use HasFactory;

    protected $casts = [
        'participant_count' => 'integer',
        'activity_chairs' => 'array',
        'target_participants_percentage' => 'integer',
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
