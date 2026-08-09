<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\Sdg;
use Database\Factories\CalendarActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $activity_calendar_id
 * @property string $name
 * @property string|null $description
 * @property string $venue
 * @property Carbon $activity_date
 * @property string $start_time
 * @property string $end_time
 * @property Sdg|null $sdg
 * @property string|null $participant_program_assigned
 * @property float|null $budget
 */
#[Fillable(['activity_calendar_id', 'name', 'description', 'venue', 'activity_date', 'start_time', 'end_time', 'sdg', 'participant_program_assigned', 'budget'])]
class CalendarActivity extends Model
{
    /** @use HasFactory<CalendarActivityFactory> */
    use HasFactory;

    protected $casts = [
        'activity_date' => 'date',
        'sdg' => Sdg::class,
        'budget' => 'decimal:2',
    ];

    /** @return BelongsTo<ActivityCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class, 'activity_calendar_id');
    }

    /**
     * Restricts to activities whose parent document has been fully
     * Approved. This is the authorization boundary for anything shown to
     * a public, unauthenticated audience (the landing-page activities
     * widget) — never bypass it for a guest-facing query. A
     * Draft/InReview/Returned/Rejected document's activities must never
     * reach a guest through this scope.
     *
     * @param  Builder<CalendarActivity>  $query
     * @return Builder<CalendarActivity>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereHas(
            'calendar.document',
            fn (Builder $q) => $q->where('status', DocumentStatus::Approved->value),
        );
    }

    /**
     * start_time/end_time are native SQL TIME columns with no Eloquent
     * cast; PostgreSQL's driver returns them as "H:i:s" (e.g. "09:00:00"),
     * not the bare "H:i" every form's `date_format:H:i` validation rule
     * requires. Read-only accessors normalize this at the single point
     * every controller/frontend already reads through — mirroring how
     * `activity_date` is normalized via its own cast above — so a
     * previously-stored value redisplayed on an edit/resubmit form always
     * round-trips cleanly. Writes are untouched: validated "H:i" input
     * still goes straight to the column as-is.
     */
    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value)->format('H:i') : null,
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value)->format('H:i') : null,
        );
    }
}
