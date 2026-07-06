<?php

namespace App\Organizations;

use App\Enums\OfficerPosition;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Adviser-initiated officer binding.
 *
 * Invariant: at most one active president and one active secretary per org.
 * On turnover the old holder is deactivated (never deleted) and a new
 * active membership is created.
 */
class BindOrganizationOfficer
{
    public function __construct(private readonly RoleDirectory $roleDirectory) {}

    /**
     * @throws AuthorizationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        User $student,
        OfficerPosition $position,
        ?string $academicYear = null,
    ): OrganizationMembership {
        $adviser = $this->roleDirectory->adviserFor($organization);

        if ($actor->id !== $adviser->id) {
            throw new AuthorizationException('Only the org\'s adviser may bind officers.');
        }

        return DB::transaction(function () use ($organization, $student, $position, $academicYear) {
            // Turnover: deactivate any existing active holder of this position.
            OrganizationMembership::query()
                ->where('organization_id', $organization->id)
                ->where('position', $position->value)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return OrganizationMembership::create([
                'user_id' => $student->id,
                'organization_id' => $organization->id,
                'position' => $position->value,
                'academic_year' => $academicYear ?? AcademicYear::current(),
                'is_active' => true,
            ]);
        });
    }
}
