<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdviserTermOutcome;
use App\Enums\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignOrganizationAdviserRequest extends FormRequest
{
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
            'adviser_id' => ['required', 'integer', Rule::exists('role_assignments', 'user_id')->where('role', Role::Adviser->value)],
            // Only meaningful when the organization has a current adviser.
            'outgoing_adviser' => ['required', Rule::enum(AdviserTermOutcome::class)],
            // The adviser the page showed (empty when it showed none). The action
            // refuses if it has changed since, so a confirmation about one
            // adviser can never replace — or deactivate — another.
            'current_adviser_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
