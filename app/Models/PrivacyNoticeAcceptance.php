<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One acceptance of the Privacy Notice, written every time a user accepts it
 * (once per login). Append-only: never updated or deleted by the app, same
 * spirit as DocumentRemark and DocumentTransition.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $notice_version
 * @property string|null $ip_address
 * @property CarbonInterface $accepted_at
 */
#[Fillable(['user_id', 'notice_version', 'ip_address', 'accepted_at'])]
class PrivacyNoticeAcceptance extends Model
{
    public $timestamps = false;

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
