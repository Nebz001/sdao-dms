<?php

namespace App\Http\Requests\Registrations;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRegistrationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Extra-Curricular orgs are university-wide and have no college
            // (Phase 2 remediation item 3) — required only for Co-Curricular.
            'school_id' => ['nullable', 'integer', 'exists:schools,id', 'required_if:organization_type,'.OrganizationType::CoCurricular->value],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            // Must be a real, admin-provisioned adviser account — role-scoped
            // so a valid-but-non-adviser user id is rejected here, not just
            // deeper in SubmitOrganizationRegistration.
            'adviser_id' => ['required', 'integer', Rule::exists('role_assignments', 'user_id')->where('role', Role::Adviser->value)],
            'organization_type' => ['required', 'string', Rule::enum(OrganizationType::class)],
            'purpose_of_organization' => ['required', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_no' => ['required', 'string', 'max:50'],
            'email_address' => ['required', 'email', 'max:255'],
            'date_organized' => ['required', 'date'],
            // Phase 2 item 8 — every attachment listed on the client's real
            // form is required, no conditionals on Organization Type.
            ...AttachmentSlots::validationRules(FormType::OrganizationRegistration, requiredAtWrite: true),
        ];
    }

    /**
     * Exact field corrections (Phase 2 item 7 slice 2) — auto-generated
     * validation messages read using the client's real-form wording.
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
            'school_id' => 'College',
            ...AttachmentSlots::validationAttributes(FormType::OrganizationRegistration),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'adviser_id.required' => 'Select an adviser from the search list. If none appear, contact SDAO.',
            'adviser_id.exists' => 'Select an adviser from the search list. If none appear, contact SDAO.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $organizationType = OrganizationType::tryFrom($this->string('organization_type')->toString());
            $schoolId = $this->input('school_id') !== null ? $this->integer('school_id') : null;
            $programId = $this->input('program_id') !== null ? $this->integer('program_id') : null;

            // Extra-Curricular orgs are university-wide and have no college —
            // reject a half-set combination outright rather than silently
            // dropping whichever the client sent. Without this, a college
            // could get attached to an org that RoleDirectory/
            // ProposalVariantResolver assume has none (they key off
            // hasNoSchool(), not organization_type).
            if ($organizationType === OrganizationType::ExtraCurricular) {
                if ($schoolId !== null) {
                    $validator->errors()->add('school_id', 'An Extra-Curricular organization has no college — leave this blank.');
                }

                if ($programId !== null) {
                    $validator->errors()->add('program_id', 'An Extra-Curricular organization has no program — leave this blank.');
                }

                return;
            }

            if ($programId !== null) {
                $belongsToSchool = Program::where('id', $programId)->where('school_id', $schoolId)->exists();

                if (! $belongsToSchool) {
                    $validator->errors()->add('program_id', 'The selected program does not belong to the selected school.');
                }
            }
        });
    }
}
