<?php

namespace App\Http\Requests\Proposals;

use App\Enums\Term;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'objectives' => ['required', 'string'],
            'narrative' => ['required', 'string'],
            'estimated_budget' => ['nullable', 'numeric', 'min:0'],
            // Optional off-calendar activity update fields
            'title' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'activity_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'term' => ['nullable', Rule::enum(Term::class)],
            // Optional on-calendar activity re-link
            'calendar_activity_id' => ['nullable', 'integer', Rule::exists('calendar_activities', 'id')],
        ];
    }
}
