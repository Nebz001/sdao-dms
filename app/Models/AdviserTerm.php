<?php

namespace App\Models;

use App\Enums\AdviserTermOutcome;
use Carbon\CarbonInterface;
use Database\Factories\AdviserTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stretch of time a user was an organization's adviser. History only:
 * RoleDirectory still answers "who is the adviser now" from role_assignments.
 * A swap closes the old term and opens a new one (AssignOrganizationAdviser);
 * rows are never deleted by the app.
 *
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at null while the term is open
 * @property int|null $started_by Who bound them; null on a term backfilled from before this was recorded.
 * @property int|null $ended_by
 * @property AdviserTermOutcome|null $end_outcome
 */
#[Fillable(['user_id', 'organization_id', 'started_at', 'ended_at', 'started_by', 'ended_by', 'end_outcome'])]
class AdviserTerm extends Model
{
    /** @use HasFactory<AdviserTermFactory> */
    use HasFactory;

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'end_outcome' => AdviserTermOutcome::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /** @param  Builder<AdviserTerm>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('ended_at');
    }
}
