<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvisionApproverRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true; // Gated by the `access-admin` route middleware; re-checked in the action.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(audience: 'staff'),
            'id_number' => $this->idNumberRules(required: false),
            'role' => ['required', 'string', Rule::enum(Role::class)->except([Role::Student])],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }
}
