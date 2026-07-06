<?php

namespace App\Models;

use App\Enums\Term;
use Database\Factories\ActivityCalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $document_id
 * @property string $academic_year
 * @property Term $term
 */
#[Fillable(['document_id', 'academic_year', 'term'])]
class ActivityCalendar extends Model
{
    /** @use HasFactory<ActivityCalendarFactory> */
    use HasFactory;

    protected $casts = [
        'term' => Term::class,
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return HasMany<CalendarActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(CalendarActivity::class)->orderBy('activity_date')->orderBy('start_time');
    }
}
