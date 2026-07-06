<?php

namespace App\Http\Requests\Calendar;

use App\Enums\Term;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via Gate
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'term' => ['required', Rule::enum(Term::class)],
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:255'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.activity_date' => ['required', 'date'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i', 'after:activities.*.start_time'],
            'activities.*.description' => ['nullable', 'string'],
        ];
    }
}
