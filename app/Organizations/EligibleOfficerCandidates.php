<?php

namespace App\Organizations;

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\Role;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is eligible to be bound as an officer of a given organization — the
 * single source for both the adviser's direct bind picker
 * (OrganizationOfficerController) and the officer-change-request nominee
 * typeahead (App\Organizations\RequestOfficerChange), so the two paths'
 * eligibility rules can never drift apart. Extracted verbatim from
 * OrganizationOfficerController::index(), which is a behaviour-preserving
 * refactor, not a rule change.
 */
class EligibleOfficerCandidates
{
    /**
     * Candidates the adviser can bind: never an account holding an approver
     * role (RoleAssignment is the only thing that knows an account is an
     * adviser/chair/dean/etc. — OrganizationMembership has no concept of
     * it), must be SDAO-Verified (BindOrganizationOfficer rejects an
     * unverified/rejected student anyway — filtered here too so nobody sees
     * an un-bindable candidate), AND either a bare account (no
     * OrganizationMembership row at all — the shape a self-registered
     * student has) OR an account currently ACTIVE in THIS org. Using
     * is_active (not mere row existence) means a former officer whose
     * membership was deactivated on turnover is correctly excluded, not
     * perpetually "known."
     *
     * One organization per student (Phase 2 item 4): also hides anyone with
     * an in-flight (Draft/InReview/Returned) registration for a DIFFERENT
     * org — they'd immediately trip BindOrganizationOfficer's/
     * SubmitOrganizationRegistration's guards anyway, so nobody sees an
     * un-bindable candidate in a picker.
     *
     * @return Builder<User>
     */
    public function query(Organization $organization, string $search = ''): Builder
    {
        $inFlightElsewhereUserIds = Document::query()
            ->where('form_type', FormType::OrganizationRegistration->value)
            ->where('organization_id', '!=', $organization->id)
            ->whereIn('status', [
                DocumentStatus::Draft->value,
                DocumentStatus::InReview->value,
                DocumentStatus::Returned->value,
            ])
            ->pluck('submitted_by');

        return User::query()
            ->whereDoesntHave('roleAssignments', fn ($q) => $q->where('role', '!=', Role::Student->value))
            ->where('account_status', AccountStatus::Verified->value)
            ->where(function ($query) use ($organization) {
                $query->whereDoesntHave('organizationMemberships')
                    ->orWhereHas('organizationMemberships', fn ($q) => $q
                        ->where('organization_id', $organization->id)
                        ->active()
                    );
            })
            ->whereNotIn('id', $inFlightElsewhereUserIds)
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy('name');
    }

    /**
     * Whether a specific student is an eligible candidate for this org right
     * now — the finalize-time re-check used by
     * App\Organizations\Admin\ApproveOfficerChange, since a request may sit
     * Pending long enough for eligibility to have changed.
     */
    public function matches(Organization $organization, User $student): bool
    {
        return $this->query($organization)->whereKey($student->id)->exists();
    }
}
