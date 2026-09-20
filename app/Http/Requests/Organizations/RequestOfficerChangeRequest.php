<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OfficerPosition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestOfficerChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in RequestOfficerChange (the actor's own active membership).
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'string', Rule::enum(OfficerPosition::class)],
            'nominee_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
