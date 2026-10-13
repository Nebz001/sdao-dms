<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\Sdg;
use Database\Factories\CalendarActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $activity_calendar_id
 * @property string $name
 * @property string|null $description
 * @property string $venue
 * @property Carbon $activity_date
 * @property string $start_time
 * @property string $end_time
 * @property Collection<int, Sdg>|null $sdg Multi-select (Group B item 1) —
 *                                          at least one goal per activity in real student submissions
 *                                          (StoreActivityCalendarRequest enforces min:1), stored as a json array;
 *                                          mirrors ActivityProposal::$partner_organizations' array shape.
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
        'sdg' => AsEnumCollection::class.':'.Sdg::class,
        'budget' => 'decimal:2',
    ];

    /** @return BelongsTo<ActivityCalendar, $this> */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ActivityCalendar::class, 'activity_calendar_id');
    }

    /** @return HasMany<ActivityProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(ActivityProposal::class, 'calendar_activity_id');
    }

    /**
     * THE publication rule: an activity is on the centralized calendar, the
     * public landing page and the hard-block side of conflict checks only
     * when an Activity Proposal linked to it (an on-calendar proposal against
     * the planned row, or an off-calendar proposal's own private row) has
     * reached final Approval. The Activity Calendar document's own status
     * decides nothing: an approved calendar is only the planning list.
     * Every reader must use this scope so the rule cannot drift; it is also
     * the authorization boundary for the unauthenticated landing page.
     *
     * @param  Builder<CalendarActivity>  $query
     * @return Builder<CalendarActivity>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas(
            'proposals.document',
            fn (Builder $q) => $q->where('status', DocumentStatus::Approved->value),
        );
    }

    /**
     * Tentative entries for the logged-in venue calendar: activities whose
     * linked Activity Proposal is In Review (in routing, not yet fully
     * approved), on-calendar and off-calendar alike. Drafts, returned and
     * rejected proposals, and planned entries with no proposal are never
     * tentative. A row that is also published() is the caller's to drop, so
     * only the published one shows.
     *
     * @param  Builder<CalendarActivity>  $query
     * @return Builder<CalendarActivity>
     */
    public function scopeTentative(Builder $query): Builder
    {
        return $query->whereHas(
            'proposals.document',
            fn (Builder $q) => $q->where('status', DocumentStatus::InReview->value),
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
