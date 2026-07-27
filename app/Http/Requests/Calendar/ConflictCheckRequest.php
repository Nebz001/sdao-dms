<?php

namespace App\Http\Requests\Calendar;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConflictCheckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            // Hardening: cap batch size so a single request can't fan out
            // into an unbounded number of VenueConflictChecker queries — 20
            // comfortably covers a real activity calendar submission (a
            // term's worth of activities).
            'activities' => ['required', 'array', 'min:1', 'max:20'],
            'activities.*.venue' => ['required', 'string'],
            'activities.*.activity_date' => ['required', 'date'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }
}
