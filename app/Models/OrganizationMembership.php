<?php

namespace App\Models;

use App\Enums\OfficerPosition;
use Database\Factories\OrganizationMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property OfficerPosition $position
 * @property string $academic_year
 * @property bool $is_active
 */
#[Fillable(['user_id', 'organization_id', 'position', 'academic_year', 'is_active'])]
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory;

    protected $casts = [
        'position' => OfficerPosition::class,
        'is_active' => 'bool',
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

    /** @param  Builder<OrganizationMembership>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
