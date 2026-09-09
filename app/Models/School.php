<?php

namespace App\Models;

use App\Enums\SchoolType;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property SchoolType $type
 */
#[Fillable(['name', 'type'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected $casts = [
        'type' => SchoolType::class,
    ];

    /**
     * Hard-closes the school list to exactly the 4 real schools (SACE, SAHS,
     * SABM, SHS — structural fix, 2026-09-09 plan). There is no schools admin
     * UI at all; the only write path is a seeder, so this is a cheap,
     * always-on guard against any future accidental write path.
     *
     * Scoped to the pgsql connection only (i.e. the real, production
     * database) — never sqlite. Under sqlite (every Pest test's own isolated
     * in-memory DB), OrganizationFactory/ProgramFactory freely mint fresh
     * throwaway schools for test isolation, unrelated to and unconstrained by
     * the real-world "exactly 4" requirement this guard exists to enforce.
     */
    protected static function booted(): void
    {
        static::creating(function (School $school) {
            if ($school->enforcesClosedSet() && static::wouldExceedClosedSet()) {
                throw new \LogicException('Cannot create a 5th school — exactly 4 (SACE, SAHS, SABM, SHS) are supported.');
            }
        });

        static::deleting(function (School $school) {
            if ($school->enforcesClosedSet()) {
                throw new \LogicException('Schools cannot be deleted.');
            }
        });
    }

    /**
     * Whether the closed-set-of-4 rule applies to this model's current
     * connection — the real Postgres database only, never sqlite. Split out
     * from booted() so the counting rule below (wouldExceedClosedSet) stays
     * unit-testable under the Pest suite's own sqlite connection, independent
     * of whether enforcement itself is currently active there.
     */
    public function enforcesClosedSet(): bool
    {
        return $this->getConnection()->getDriverName() === 'pgsql';
    }

    /** Pure counting rule: would one more school exceed the closed set of 4? */
    public static function wouldExceedClosedSet(): bool
    {
        return static::count() >= 4;
    }

    /** @return HasMany<Program, $this> */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /** @return HasMany<Organization, $this> */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    public function isRegular(): bool
    {
        return $this->type === SchoolType::Regular;
    }

    public function isSeniorHigh(): bool
    {
        return $this->type === SchoolType::SeniorHigh;
    }
}
