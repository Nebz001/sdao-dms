<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string $purpose
 * @property string $code_hash
 * @property array<string, mixed>|null $payload
 * @property int|null $user_id
 * @property int $attempts
 * @property Carbon|null $locked_until
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable(['email', 'purpose', 'code_hash', 'payload', 'user_id', 'attempts', 'locked_until', 'expires_at', 'consumed_at'])]
class EmailVerificationCode extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'locked_until' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
