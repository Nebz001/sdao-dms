<?php

namespace App\Enums;

use App\Models\Organization;

enum OrganizationType: string
{
    case CoCurricular = 'co_curricular';
    case ExtraCurricular = 'extra_curricular';

    public function label(): string
    {
        return match ($this) {
            self::CoCurricular => 'Co-Curricular',
            self::ExtraCurricular => 'Extra Curricular-Interest Clubs',
        };
    }

    /**
     * The single derivation point (structural fix, 2026-09-09 plan): an org
     * with a school is Co-Curricular, one without is Extra-Curricular — the
     * same fact Organization::hasNoSchool() already expresses for routing.
     * organization_type is no longer independently settable; every write site
     * computes it from here instead of trusting client input.
     */
    public static function fromSchoolId(?int $schoolId): self
    {
        return $schoolId === null ? self::ExtraCurricular : self::CoCurricular;
    }

    public static function fromOrganization(Organization $organization): self
    {
        return self::fromSchoolId($organization->school_id);
    }
}
