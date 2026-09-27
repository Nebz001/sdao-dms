<?php

namespace App\Models;

use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property AccountStatus $account_status
 * @property string|null $id_number
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'account_status', 'email_verified_at', 'id_number'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'id_number'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return HasMany<OrganizationMembership, $this> */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * SDAO's manual account-verification gate (distinct from email
     * verification): only a Verified account can submit documents or be
     * adviser-bound as an officer.
     */
    public function isVerifiedAccount(): bool
    {
        return $this->account_status === AccountStatus::Verified;
    }

    /**
     * Permanent, distinct terminal state — never revived, never deleted.
     */
    public function isRejectedAccount(): bool
    {
        return $this->account_status === AccountStatus::Rejected;
    }

    /**
     * Called ONLY from a deliberate, user-initiated password change
     * (Fortify's ResetUserPassword action and the settings password
     * update) — never from a generic `updated`/`wasChanged('password')`
     * model observer. That distinction matters: the auth guard's own
     * silent password rehash (EloquentUserProvider::rehashPasswordIfRequired(),
     * which writes a new hash of the SAME password when bcrypt's cost
     * factor changes) also changes this column, via `forceFill()->save()`
     * directly on the provider layer — it never calls through either of
     * the two sites that call this method, so a mobile approver's token
     * survives an ordinary login even when that silent rehash fires.
     *
     * Guarded for a deploy that hasn't run `php artisan migrate` yet:
     * `personal_access_tokens` may not exist in production before that,
     * and a password reset/change must still succeed.
     */
    public function revokeAllApiTokens(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            $this->tokens()->delete();
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'account_status' => AccountStatus::class,
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
