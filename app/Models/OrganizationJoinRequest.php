<?php

namespace App\Models;

use App\Enums\JoinRequestStatus;
use Database\Factories\OrganizationJoinRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property JoinRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_comment
 */
#[Fillable(['user_id', 'organization_id', 'status', 'decided_by', 'decided_at', 'decision_comment'])]
class OrganizationJoinRequest extends Model
{
    /** @use HasFactory<OrganizationJoinRequestFactory> */
    use HasFactory;

    protected $casts = [
        'status' => JoinRequestStatus::class,
        'decided_at' => 'datetime',
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
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @param  Builder<OrganizationJoinRequest>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', JoinRequestStatus::Pending->value);
    }
}
