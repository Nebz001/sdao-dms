<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OfficerPosition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BindOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in BindOrganizationOfficer action.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'string', Rule::enum(OfficerPosition::class)],
        ];
    }
}
