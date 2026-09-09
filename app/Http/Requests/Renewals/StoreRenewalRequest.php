<?php

namespace App\Http\Requests\Renewals;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Models\OrganizationMembership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_type' => ['required', 'string', Rule::enum(OrganizationType::class)],
            'purpose_of_organization' => ['required', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:50'],
            'email_address' => ['required', 'email', 'max:255'],
            'date_organized' => ['required', 'date'],
            // Phase 2 item 8 — Renewal's required list is the same 6 as
            // Registration, plus 3 more (List of Past Projects, Financial
            // Statement, Summary of Evaluation).
            ...AttachmentSlots::validationRules(FormType::OrganizationRenewal, requiredAtWrite: true),
        ];
    }

    /**
     * Exact field corrections (Phase 2 item 7 slice 2).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_type' => 'Type of Organization',
            'purpose_of_organization' => 'Purpose of Organization',
            'contact_no' => 'Contact No.',
            'email_address' => 'Email Address',
            ...AttachmentSlots::validationAttributes(FormType::OrganizationRenewal),
        ];
    }

    /**
     * Unlike registration, `organization_type` here is re-submitted against
     * an EXISTING organization whose school_id/program_id are already fixed
     * and immutable (see SubmitOrganizationRegistration — nothing else in the
     * app ever writes them). A renewal choosing a type that contradicts that
     * existing shape would recreate the exact data-integrity violation the
     * 2026_09_09_100000 migration corrects — RoleDirectory/
     * ProposalVariantResolver key off the org's actual school_id/program_id,
     * not off whatever organization_type a renewal happens to submit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $organizationType = OrganizationType::tryFrom($this->string('organization_type')->toString());
            $organization = OrganizationMembership::query()
                ->with('organization')
                ->where('user_id', $this->user()->id)
                ->where('is_active', true)
                ->first()?->organization;

            if ($organization === null) {
                return;
            }

            if ($organizationType === OrganizationType::ExtraCurricular) {
                if ($organization->school_id !== null || $organization->program_id !== null) {
                    $validator->errors()->add('organization_type', 'This organization has a college/program on file and cannot renew as Extra-Curricular. Contact SDAO if this is incorrect.');
                }

                return;
            }

            if ($organizationType === OrganizationType::CoCurricular && $organization->hasNoSchool()) {
                $validator->errors()->add('organization_type', 'This organization has no college on file and cannot renew as Co-Curricular. Contact SDAO if this is incorrect.');
            }
        });
    }
}
