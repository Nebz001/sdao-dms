<?php

namespace App\Http\Requests\Organizations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeclineOfficerChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by the `can:access-admin` route middleware + DeclineOfficerChange's SDAO re-assertion.
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
