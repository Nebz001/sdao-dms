<?php

namespace App\Organizations;

use App\Enums\FormType;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentPeriod;
use Illuminate\Support\Facades\Gate;

/**
 * Whether the signed-in student can start a form of the given type right now,
 * and if not, why — for the "New …" button and the empty state on each of
 * their list pages. It only reads the answers the app already gives (the
 * dashboard's Quick Submit tiles and DocumentPolicy::propose), so a list page
 * never offers a start button the form page would refuse.
 */
final class StudentFormAvailability
{
    /**
     * @return array{enabled: bool, reason: string|null}
     */
    public static function for(User $user, FormType $formType): array
    {
        if ($formType === FormType::OrganizationRegistration) {
            $allowed = Gate::forUser($user)->allows('propose', Organization::class);

            return [
                'enabled' => $allowed,
                'reason' => $allowed ? null : 'Your organization is already registered.',
            ];
        }

        $membership = $user->organizationMemberships()->active()->first();

        if ($membership === null) {
            return ['enabled' => false, 'reason' => 'Only active officers of an organization can file this.'];
        }

        $tile = collect(StudentDashboardData::for($membership, CurrentPeriod::get())->quickSubmit())
            ->firstWhere('formType', $formType->value);

        return [
            'enabled' => (bool) ($tile['enabled'] ?? false),
            'reason' => $tile['reason'] ?? null,
        ];
    }
}
