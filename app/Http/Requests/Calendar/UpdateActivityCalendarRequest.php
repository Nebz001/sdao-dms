<?php

namespace App\Http\Requests\Calendar;

use App\Enums\Sdg;
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
            // Term is frozen at original submission (Phase 2 item 6) and is
            // never re-derived from user input on resubmit.
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:255'],
            'activities.*.venue' => ['required', 'string', 'max:255'],
            'activities.*.activity_date' => ['required', 'date', 'after_or_equal:today'],
            'activities.*.start_time' => ['required', 'date_format:H:i'],
            'activities.*.end_time' => ['required', 'date_format:H:i', 'after:activities.*.start_time'],
            'activities.*.description' => ['nullable', 'string'],
            // Exact field corrections (Phase 2 item 7 slice 1).
            // SDG multi-select (Group B item 1) — see StoreActivityCalendarRequest.
            'activities.*.sdg' => ['required', 'array', 'min:1'],
            'activities.*.sdg.*' => [Rule::enum(Sdg::class)],
            'activities.*.participant_program_assigned' => ['required', 'string', 'max:255'],
            'activities.*.budget' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Group B item 3 — see StoreActivityCalendarRequest::attributes() for
     * why this wildcard map exists.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activities.*.name' => 'Activity Name',
            'activities.*.venue' => 'Venue',
            'activities.*.activity_date' => 'Date',
            'activities.*.start_time' => 'Start Time',
            'activities.*.end_time' => 'End Time',
            'activities.*.description' => 'Description',
            'activities.*.sdg' => 'SDG',
            'activities.*.participant_program_assigned' => 'Participant/Program Assigned',
            'activities.*.budget' => 'Budget',
        ];
    }
}
