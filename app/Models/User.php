<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Support\PersonName;
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
 * @property string $name "First Last", kept in sync with first_name/last_name on save.
 * @property string|null $first_name
 * @property string|null $last_name
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
#[Fillable(['name', 'first_name', 'last_name', 'email', 'password', 'must_change_password', 'account_status', 'account_reviewed_at', 'email_verified_at', 'id_number'])]
#[Hidden(['password', 'remember_token', 'id_number'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * `name` stays a real column holding the display name (rather than an
     * accessor) because it is read in raw selects, plucks, orderBy, joins and
     * mail/notification text all over the app. It is kept in sync here:
     * - when first_name/last_name change, name is rebuilt from them, keeping
     *   any honorific the old name started with ("Dr.") so what is displayed
     *   and printed does not lose a title just because a name was corrected;
     * - when only name is written (legacy callers), first_name/last_name are
     *   derived from it, without any title or parenthesised note.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $joined = PersonName::join($user->first_name, $user->last_name);

                if ($joined === '') {
                    return;
                }

                // A caller that wrote `name` too (a seeder, a factory) wins when
                // it agrees with the first/last it wrote.
                if ($user->isDirty('name') && self::splitMatches((string) $user->name, $user)) {
                    return;
                }

                $title = $user->isDirty('name') ? '' : PersonName::leadingTitle((string) $user->getOriginal('name'));
                $user->name = PersonName::join($title, $joined);

                return;
            }

            if ($user->isDirty('name')) {
                $parts = PersonName::split((string) $user->name);

                if ($parts['first'] !== '') {
                    $user->first_name = $parts['first'];
                    $user->last_name = $parts['last'] === '' ? null : $parts['last'];
                }
            }
        });
    }

    private static function splitMatches(string $name, User $user): bool
    {
        $parts = PersonName::split($name);

        return $parts['first'] === (string) $user->first_name
            && $parts['last'] === (string) $user->last_name;
    }

    /**
     * Matches a person by first name, last name, or the full name
     * ("First Last"). Same plain `like` as every other search in the app.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeWhereNameMatches(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where($query->qualifyColumn('name'), 'like', "%{$search}%")
            ->orWhere($query->qualifyColumn('first_name'), 'like', "%{$search}%")
            ->orWhere($query->qualifyColumn('last_name'), 'like', "%{$search}%"));
    }

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
