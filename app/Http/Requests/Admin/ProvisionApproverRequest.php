<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProvisionApproverRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true; // Gated by the `access-admin` route middleware; re-checked in the action.
    }

    /**
     * Login lowercases the email it looks up, so the address is stored
     * lowercase too. Otherwise a mixed-case entry (Magpantay@gmail.com) could
     * never be matched by the lowercased login lookup.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            // Any valid address except a student-domain one: an approver may
            // be reached on a personal mailbox. Students and profile email
            // changes keep the school-domain rule (see ProfileValidationRules).
            'email' => $this->emailRules(allowPersonal: true),
            'replaces_user_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf(fn () => $this->input('role') !== Role::SdaoMember->value),
                Rule::exists('role_assignments', 'user_id')->where('role', Role::SdaoMember->value),
            ],
            'id_number' => $this->idNumberRules(required: false),
            'role' => ['required', 'string', Rule::enum(Role::class)->except([Role::Student])],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }
}
