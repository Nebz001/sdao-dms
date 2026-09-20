<?php

namespace App\Models;

use App\Enums\OfficerPosition;
use Carbon\CarbonInterface;
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
 * @property CarbonInterface|null $started_at When this term began. Backfilled from
 *                                            created_at for every row that predates this column.
 * @property CarbonInterface|null $ended_at When this term ended — pure history, NEVER
 *                                          a read predicate (is_active is, always).
 *                                          Null while is_active is true (current term),
 *                                          or on a legacy row deactivated before this
 *                                          column existed, whose real end date was
 *                                          never recorded and is not guessed.
 */
#[Fillable(['user_id', 'organization_id', 'position', 'academic_year', 'is_active', 'started_at', 'ended_at'])]
class OrganizationMembership extends Model
{
    /** @use HasFactory<OrganizationMembershipFactory> */
    use HasFactory;

    protected $casts = [
        'position' => OfficerPosition::class,
        'is_active' => 'bool',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
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

    /**
     * The sole discriminator for "currently holds this seat" — never
     * ended_at. Do not "simplify" this to `whereNull('ended_at')`: a legacy
     * row deactivated before term dates existed has ended_at=null but is not
     * current (see the class docblock's three-state note).
     *
     * @param  Builder<OrganizationMembership>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
