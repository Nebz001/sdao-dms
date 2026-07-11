<?php

namespace App\Http\Requests\Renewals;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Enums\OrganizationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRenewalRequest extends FormRequest
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
            // Phase 2 item 8 — every slot nullable at Update; already-uploaded
            // required attachments aren't forced to be re-uploaded on every
            // resubmit (AttachmentStorage::assertRequiredSlotsFilled is the
            // real completeness gate here, checking persisted rows too).
            ...AttachmentSlots::validationRules(FormType::OrganizationRenewal, requiredAtWrite: false),
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
}
