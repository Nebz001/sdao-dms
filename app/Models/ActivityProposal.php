<?php

namespace App\Models;

use App\Enums\ProposalCalendarMode;
use Database\Factories\ActivityProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $document_id
 * @property ProposalCalendarMode $calendar_mode
 * @property int|null $calendar_activity_id
 * @property string $title
 * @property string|null $objectives
 * @property string|null $narrative
 * @property float|null $estimated_budget
 * @property int $form_step
 */
#[Fillable(['document_id', 'calendar_mode', 'calendar_activity_id', 'title', 'objectives', 'narrative', 'estimated_budget', 'form_step'])]
class ActivityProposal extends Model
{
    /** @use HasFactory<ActivityProposalFactory> */
    use HasFactory;

    protected $casts = [
        'calendar_mode' => ProposalCalendarMode::class,
        'form_step' => 'integer',
        'estimated_budget' => 'decimal:2',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<CalendarActivity, $this> */
    public function calendarActivity(): BelongsTo
    {
        return $this->belongsTo(CalendarActivity::class);
    }

    /** @return HasMany<ProposalAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProposalAttachment::class);
    }
}
