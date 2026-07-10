<?php

namespace App\Http\Requests\Registrations;

use App\Enums\OrganizationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegistrationRequest extends FormRequest
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
            'roster' => ['nullable', 'array'],
            'roster.*' => ['string', 'max:255'],
            // Optional: Phase 2 item 5 — the student may pick a different
            // adviser when resubmitting after a return-for-revision.
            'adviser_id' => ['nullable', 'integer', 'exists:users,id'],
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
        ];
    }
}
