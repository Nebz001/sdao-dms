<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property AccountStatus $account_status
 * @property string|null $id_number
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $remember_token
 * @property Carbon|null $account_reviewed_at
 * @property Carbon|null $deactivated_at
 * @property string|null $deactivated_reason
 * @property int|null $deactivated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'must_change_password', 'account_status', 'account_reviewed_at', 'email_verified_at', 'id_number'])]
#[Hidden(['password', 'remember_token', 'id_number'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable;

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

    /** @return HasMany<PushToken, $this> */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deactivated_by');
    }

    /**
     * A deactivated account keeps its row, its name and every history row
     * that references it, but can no longer sign in or act. A column rather
     * than SoftDeletes on purpose: a soft delete scope would blank the
     * actor shown on the append-only audit log.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('deactivated_at'));
    }

    /**
     * Accounts that are not students: no student-domain email, no
     * organization membership and no Student role row. Admin deactivation is
     * limited to these for now.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeNonStudent(Builder $query): Builder
    {
        foreach (config('school.email_domains.student', []) as $domain) {
            $query->whereRaw('LOWER(email) NOT LIKE ?', ['%@'.strtolower($domain)]);
        }

        return $query
            ->whereDoesntHave('organizationMemberships')
            ->whereDoesntHave('roleAssignments', fn ($q) => $q->where('role', Role::Student->value));
    }

    public function isStudentAccount(): bool
    {
        return ! self::query()->nonStudent()->whereKey($this->id)->exists();
    }

    /**
     * A deactivated account gets no reset link: it must not be able to get
     * back in through the password reset flow.
     */
    public function sendPasswordResetNotification(#[SensitiveParameter] $token): void
    {
        if ($this->isDeactivated()) {
            return;
        }

        $this->notify(new ResetPassword($token));
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
     * Ends every database session this user holds except the one named, so a
     * password change signs out other browsers while keeping the current one.
     * Pass null to end all of them. A no-op for non-database session drivers
     * and for a deploy where the sessions table does not exist yet.
     */
    public function endOtherSessions(?string $exceptSessionId = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('user_id', $this->id)
            ->when($exceptSessionId !== null, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
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
            'must_change_password' => 'boolean',
            'deactivated_at' => 'datetime',
            'account_reviewed_at' => 'datetime',
        ];
    }
}
