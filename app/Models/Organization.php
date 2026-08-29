<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int|null $school_id Null for an Extra-Curricular org with no college (see hasNoSchool()).
 * @property int|null $program_id Null for SHS orgs (belong directly to SHS) and for a college-less org.
 */
#[Fillable(['name', 'school_id', 'program_id'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Null for organizations that belong directly to Senior High School.
     *
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /**
     * NOT `$this->program_id === null` — that used to be sufficient (only a
     * genuine SHS org had no program), but an Extra-Curricular org with no
     * college (see hasNoSchool()) also has a null program_id without being
     * SHS. Checking the school's own type is unambiguous either way.
     */
    public function belongsToSeniorHighSchool(): bool
    {
        return $this->school?->type === 'senior_high';
    }

    /**
     * True for an Extra-Curricular org, which is university-wide and has no
     * college — see StoreRegistrationRequest's conditional school_id
     * requirement. Routes through the ExtraCurricular* proposal variants
     * (ProposalVariantResolver), which skip both the program-chair and dean
     * steps.
     */
    public function hasNoSchool(): bool
    {
        return $this->school_id === null;
    }
}
