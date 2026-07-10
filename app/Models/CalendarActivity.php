<?php

namespace App\Models;

use App\Enums\Sdg;
use Database\Factories\CalendarActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
}
