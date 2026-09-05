<?php

namespace App\Renewals;

use App\Approval\ApprovalEngine;
use App\Attachments\AttachmentStorage;
use App\Enums\DocumentStatus;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\RenewalEligibility;
use App\Identity\RoleDirectory;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationRegistrationDetail;
use App\Models\User;
use App\Organizations\OrganizationMembershipService;
use App\Support\AcademicPeriod;
use App\Support\CurrentPeriod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renewal does NOT start from scratch: it carries forward the organization's
 * most-recent APPROVED registration/renewal data. It is capped at one
 * non-rejected renewal per COVERED academic year, and is only submittable
 * while the current term is 3rd — renewal season. A renewal filed during 3rd
 * term of academic year X covers X+1; a registration approved during 3rd term
 * of X covers X AND X+1 (grace), so a newly founded org is never asked to
 * renew in the very season it was founded in.
 *
 * eligibilityFor() is the single source of truth for all of this — never
 * re-derive renewal eligibility anywhere else. OrganizationStatusResolver's
 * `renewalDue` flag must always agree with it (pinned by a test).
 *
 * Registration stamps its coverage at APPROVE time (see
 * ApproveOrganizationRegistration) because it records when the org became
 * active. Renewal stamps its coverage at SUBMIT time, deliberately — its
 * coverage year is the uniqueness key hasNonRejectedRenewalCovering()
 * matches on, and must exist while the renewal is still InReview. Do not
 * unify these two.
 */
class SubmitOrganizationRenewal
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly OrganizationMembershipService $membershipService,
        private readonly RoleDirectory $roleDirectory,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    /**
     * @param  array<string, UploadedFile|array<int, UploadedFile>>  $attachmentFiles
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $actor,
        Organization $organization,
        OrganizationType $organizationType,
        string $purposeOfOrganization,
        string $contactPerson,
        string $contactNo,
        string $emailAddress,
        string $dateOrganized,
        array $attachmentFiles = [],
    ): Document {
        $membership = $this->membershipService->activeMembershipFor($actor, $organization);

        if ($membership === null) {
            throw new AuthorizationException('You must be an active officer of this organization to submit a renewal.');
        }

        $eligibility = $this->eligibilityFor($organization);

        if (! $eligibility->isEligible()) {
            $key = $eligibility->status === RenewalEligibility::NoPriorRecord ? 'organization' : 'period';

            throw ValidationException::withMessages([$key => $eligibility->message()]);
        }

        $period = $eligibility->currentPeriod;
        $coversAcademicYear = $period->nextAcademicYear();
        $adviser = $this->roleDirectory->adviserFor($organization);

        return DB::transaction(function () use (
            $actor, $organization, $organizationType, $purposeOfOrganization,
            $contactPerson, $contactNo, $emailAddress, $dateOrganized,
            $adviser, $period, $coversAcademicYear, $attachmentFiles
        ) {
            $document = Document::create([
                'form_type' => FormType::OrganizationRenewal,
                'variant' => null,
                'title' => "Organization Renewal — {$organization->name} ({$coversAcademicYear})",
                'status' => DocumentStatus::Draft,
                'current_step_position' => null,
                'organization_id' => $organization->id,
                'workflow_template_id' => null,
                'submitted_by' => $actor->id,
            ]);

            OrganizationRegistrationDetail::create([
                'document_id' => $document->id,
                'organization_type' => $organizationType->value,
                'purpose_of_organization' => $purposeOfOrganization,
                'contact_person' => $contactPerson,
                'contact_no' => $contactNo,
                'email_address' => $emailAddress,
                'date_organized' => $dateOrganized,
                'adviser_id' => $adviser->id,
                // Renewal's coverage is fixed at SUBMIT time, unlike a
                // registration's (see class docblock) — it must exist while
                // this renewal is InReview, since it's the uniqueness key
                // hasNonRejectedRenewalCovering() matches on.
                'academic_year' => $period->academicYear,
                'term' => $period->term->value,
                'covers_academic_year' => $coversAcademicYear,
            ]);

            // Phase 2 item 8 — every attachment listed on the client's real
            // form is required, no conditionals on Organization Type.
            $this->attachmentStorage->storeMany($document, $attachmentFiles, $actor);
            $this->attachmentStorage->assertRequiredSlotsFilled($document);

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
     * The single source of truth for whether an organization may submit a
     * renewal right now. execute() (write path), RenewalController::create()
     * (UX), and OrganizationStatusResolver's `renewalDue` flag must all go
     * through this method — never re-derive the rule elsewhere.
     *
     * $asOf defaults to the real stored current period, but can be overridden
     * to evaluate against a hypothetical one — used by
     * Admin\CurrentPeriodController to preview how many organizations would
     * come due for a candidate period BEFORE the admin actually saves it,
     * without mutating any real state.
     */
    public function eligibilityFor(Organization $organization, ?AcademicPeriod $asOf = null): RenewalEligibilityResult
    {
        $current = $asOf ?? CurrentPeriod::get();
        $record = $this->mostRecentApprovedRecord($organization);

        if ($record === null) {
            return new RenewalEligibilityResult(RenewalEligibility::NoPriorRecord, $current, $organization->name, null, null);
        }

        $coversThrough = $record->registrationDetail?->covers_academic_year;

        if (! $current->isRenewalSeason()) {
            return new RenewalEligibilityResult(RenewalEligibility::SeasonClosed, $current, $organization->name, $coversThrough, $record);
        }

        $nextYear = $current->nextAcademicYear();

        if ($this->hasNonRejectedRenewalCovering($organization, $nextYear)) {
            return new RenewalEligibilityResult(RenewalEligibility::AlreadyFiledThisYear, $current, $organization->name, $coversThrough, $record);
        }

        // Grace: a registration approved during THIS season already covers
        // next year — a brand-new org must not be asked to renew days after
        // being founded.
        if ($record->form_type === FormType::OrganizationRegistration && $coversThrough === $nextYear) {
            return new RenewalEligibilityResult(RenewalEligibility::NotYetDue, $current, $organization->name, $coversThrough, $record);
        }

        return new RenewalEligibilityResult(RenewalEligibility::Eligible, $current, $organization->name, $coversThrough, $record);
    }

    /**
     * Uniqueness guard: at most one non-rejected renewal per org per COVERED
     * academic year. Only a Rejected renewal frees the slot — reject is
     * terminal, so the org must be able to file a brand-new renewal for the
     * same covered year (invariant #2: a rejected document is never revived;
     * the student files anew).
     */
    public function hasNonRejectedRenewalCovering(Organization $organization, string $academicYear): bool
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->where('form_type', FormType::OrganizationRenewal->value)
            ->where('status', '!=', DocumentStatus::Rejected->value)
            ->whereHas('registrationDetail', fn ($q) => $q->where('covers_academic_year', $academicYear))
            ->exists();
    }
}
