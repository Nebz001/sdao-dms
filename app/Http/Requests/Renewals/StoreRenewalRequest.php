<?php

namespace App\Http\Requests\Renewals;

use App\Enums\OrganizationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'description' => ['required', 'string', 'max:5000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'email', 'max:255'],
            'date_organized' => ['required', 'date'],
            'roster' => ['nullable', 'array'],
            'roster.*' => ['string', 'max:255'],
        ];
    }
}
