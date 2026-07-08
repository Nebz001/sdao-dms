<?php

namespace App\Renewals;

use App\Approval\ApprovalEngine;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicYear;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renewal does NOT start from scratch: it carries forward the organization's
 * most-recent APPROVED registration/renewal data, and is capped at one
 * (non-rejected) renewal per organization per academic year (invariant: the
 * prior year's record is preserved — never deleted or overwritten).
 */
class SubmitOrganizationRenewal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly RoleDirectory $roleDirectory,
    ) {}

    /**
     * @param  array<int, string>|null  $roster
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        OrganizationType $organizationType,
        string $description,
        string $contactPerson,
        string $contactNumber,
        string $contactEmail,
        string $dateOrganized,
        ?array $roster = null,
    ): Document {
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer of this organization to submit a renewal.');
        }

        if ($this->mostRecentApprovedRecord($organization) === null) {
            throw ValidationException::withMessages([
                'organization' => 'This organization has no prior approved registration to renew.',
            ]);
        }

        $academicYear = AcademicYear::current();

        if ($this->hasNonRejectedRenewal($organization, $academicYear)) {
            throw ValidationException::withMessages([
                'academic_year' => "A renewal for {$academicYear} has already been filed for this organization.",
            ]);
        }

        $adviser = $this->roleDirectory->adviserFor($organization);

        return DB::transaction(function () use (
            $actor, $organization, $organizationType, $description,
            $contactPerson, $contactNumber, $contactEmail, $dateOrganized,
            $roster, $adviser, $academicYear
        ) {
            $document = Document::create([
                'form_type' => FormType::OrganizationRenewal,
                'variant' => null,
                'title' => "Organization Renewal — {$organization->name} ({$academicYear})",
                'status' => DocumentStatus::Draft,
                'current_step_position' => null,
                'organization_id' => $organization->id,
                'workflow_template_id' => null,
                'submitted_by' => $actor->id,
            ]);

            OrganizationRegistrationDetail::create([
                'document_id' => $document->id,
                'organization_type' => $organizationType->value,
                'description' => $description,
                'contact_person' => $contactPerson,
                'contact_number' => $contactNumber,
                'contact_email' => $contactEmail,
                'date_organized' => $dateOrganized,
                'adviser_id' => $adviser->id,
                'roster' => $roster,
                'academic_year' => $academicYear,
            ]);

            $this->engine->submit($document, $actor);
            $document->refresh();

            return $document;
        });
    }

    /**
     * The organization's most-recent Approved registration or renewal record.
     * Successive renewals carry forward from THIS record — not always the
     * original registration — so renewing an already-renewed org chains
     * forward correctly.
     */
    public function mostRecentApprovedRecord(Organization $organization): ?Document
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->whereIn('form_type', [
                FormType::OrganizationRegistration->value,
                FormType::OrganizationRenewal->value,
            ])
            ->where('status', DocumentStatus::Approved->value)
            ->with('registrationDetail')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Uniqueness guard: at most one renewal per org per academic year. Only a
     * Rejected renewal frees the slot — reject is terminal, so the org must be
     * able to file a brand-new renewal for the same year (invariant #2: a
     * rejected document is never revived; the student files anew).
     *
     * Public so RenewalController::create() can reuse this exact check for the
     * "already renewed this year" UX message — single source of truth.
     */
    public function hasNonRejectedRenewal(Organization $organization, string $academicYear): bool
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->where('form_type', FormType::OrganizationRenewal->value)
            ->where('status', '!=', DocumentStatus::Rejected->value)
            ->whereHas('registrationDetail', fn ($q) => $q->where('academic_year', $academicYear))
            ->exists();
    }
}
