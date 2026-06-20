<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $type 'regular' | 'senior_high'
 */
#[Fillable(['name', 'type'])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

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
        return $this->type === 'regular';
    }

    public function isSeniorHigh(): bool
    {
        return $this->type === 'senior_high';
    }
}
