<?php

namespace App\Http\Requests\Admin;

use App\Models\WorkflowStep;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStepLeadTimesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by the `access-admin` route middleware.
    }

    /**
     * Keyed by workflow step id: lead_times[12] = 3.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lead_times' => ['required', 'array', 'min:1'],
            'lead_times.*' => ['required', 'integer', 'min:1', 'max:'.WorkflowStep::MAX_LEAD_TIME_DAYS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lead_times.*.required' => 'Enter a number of days for every step.',
            'lead_times.*.integer' => 'Lead time must be a whole number of days.',
            'lead_times.*.min' => 'Lead time must be at least 1 day.',
            'lead_times.*.max' => 'Lead time can be at most '.WorkflowStep::MAX_LEAD_TIME_DAYS.' days.',
        ];
    }
}
