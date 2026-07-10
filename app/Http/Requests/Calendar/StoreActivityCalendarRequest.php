<?php

namespace App\Http\Requests\Calendar;

use App\Enums\Sdg;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityCalendarRequest extends FormRequest
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
            // Term is a global, admin-controlled setting (Phase 2 item 6),
            // not user input — no validation rule for it here.
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:255'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.activity_date' => ['required', 'date'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i', 'after:activities.*.start_time'],
            'activities.*.description' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 1). Required for
            // real student submissions even though the DB columns are
            // nullable (see CalendarActivity migration comment).
            'activities.*.sdg' => ['required', Rule::enum(Sdg::class)],
            'activities.*.participant_program_assigned' => ['required', 'string', 'max:255'],
            'activities.*.budget' => ['required', 'numeric', 'min:0'],
        ];
    }
}
