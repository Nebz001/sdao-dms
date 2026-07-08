<?php

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
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
            'narrative' => ['required', 'string', 'max:10000'],
            'outcomes' => ['nullable', 'string', 'max:10000'],
            'participant_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
