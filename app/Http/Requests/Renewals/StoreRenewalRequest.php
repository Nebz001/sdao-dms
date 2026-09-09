<?php

namespace App\Http\Requests\Renewals;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            // organization_type is no longer a submitted field (structural
            // fix, 2026-09-09 plan) — it's derived from the org's school_id,
            // which is immutable and was never re-submittable here in the
            // first place.
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
            'purpose_of_organization' => 'Purpose of Organization',
            'contact_no' => 'Contact No.',
            'email_address' => 'Email Address',
            ...AttachmentSlots::validationAttributes(FormType::OrganizationRenewal),
        ];
    }
}
