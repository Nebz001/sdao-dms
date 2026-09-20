<?php

namespace App\Models;

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use Database\Factories\OfficerChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $requested_by
 * @property OfficerPosition $position
 * @property int $nominee_id
 * @property int|null $outgoing_user_id Submit-time snapshot only — see the migration's docblock.
 * @property string|null $reason
 * @property OfficerChangeRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_comment
 */
#[Fillable(['organization_id', 'requested_by', 'position', 'nominee_id', 'outgoing_user_id', 'reason', 'status', 'decided_by', 'decided_at', 'decision_comment'])]
class OfficerChangeRequest extends Model
{
    /** @use HasFactory<OfficerChangeRequestFactory> */
    use HasFactory;

    protected $casts = [
        'position' => OfficerPosition::class,
        'status' => OfficerChangeRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function outgoingOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'outgoing_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @param  Builder<OfficerChangeRequest>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', OfficerChangeRequestStatus::Pending->value);
    }
}
